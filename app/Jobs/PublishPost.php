<?php

namespace App\Jobs;

use App\Alerts\Slack;
use App\Mail\PostNeedsHuman;
use App\Models\Post;
use App\Posts\PostState;
use App\Publishing\ManualNeeded;
use App\Publishing\Publishers;
use App\Publishing\PublishFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Publishes one Scheduled post to every platform it names; only an Approved post can ever be Scheduled. */
class PublishPost implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Post $post) {}

    public function backoff(): array
    {
        return [60, 600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->post->id))->dontRelease()];
    }

    public function handle(): void
    {
        $post = $this->post->fresh();
        if ($post->state !== PostState::Scheduled) {
            return; // a human moved it, or a twin job already did the work
        }

        $done = $post->external_ids ?? [];
        $manual = [];
        foreach ($post->platforms as $platform) {
            if (isset($done[$platform])) {
                continue; // published on an earlier attempt
            }
            $account = $post->client->accounts()->where('platform', $platform)->first()
                ?? throw new PublishFailed("No {$platform} account is connected for {$post->client->name}");
            try {
                $done[$platform] = Publishers::for($platform)->publish($post, $account);
                $post->forceFill(['external_ids' => $done])->save();
            } catch (ManualNeeded $e) {
                $manual[] = $e->getMessage();
            }
        }

        if ($manual) {
            $post->forceFill(['publish_error' => implode(' ', $manual)])->save();
            $post->transitionTo(PostState::ManualNeeded);
            Mail::to(self::whoToTell($post))->send($mail = new PostNeedsHuman($post, 'manual'));
            Slack::mailed($mail, route('posts.edit', $post));

            return;
        }
        $post->forceFill(['publish_error' => null])->save();
        $post->transitionTo(PostState::Published);
    }

    /** All retries used up: the post is marked Failed with the last error and a human is told. */
    public function failed(Throwable $e): void
    {
        $post = $this->post->fresh();
        if ($post->state !== PostState::Scheduled) {
            return;
        }
        $post->forceFill(['publish_error' => $e->getMessage()])->save();
        $post->transitionTo(PostState::Failed);
        Mail::to(self::whoToTell($post))->send($mail = new PostNeedsHuman($post, 'failed'));
        Slack::mailed($mail, route('posts.edit', $post));
    }

    public static function whoToTell(Post $post): string
    {
        return $post->creator?->email ?? config('mail.from.address');
    }
}
