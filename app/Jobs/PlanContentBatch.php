<?php

namespace App\Jobs;

use App\Alerts\Slack;
use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Runs `content:plan` in the background for the "Plan with Claude" button and mails the outcome to whoever pressed it. */
class PlanContentBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Trend research plus one caption call per post takes minutes; stays under the database queue's retry_after. */
    public int $timeout = 900;

    /** @param  array{posts: int, platforms: list<string>, from: string, days: int, brief?: ?string, instruction?: string, latest_brief?: bool}  $options */
    public function __construct(public Client $client, public User $user, public array $options) {}

    public function handle(): void
    {
        $args = ['client' => $this->client->slug, '--posts' => $this->options['posts'], '--platforms' => implode(',', $this->options['platforms']), '--from' => $this->options['from'], '--days' => $this->options['days']];
        if ($this->options['brief'] ?? null) {
            file_put_contents($args['--brief'] = tempnam(sys_get_temp_dir(), 'brief'), $this->options['brief']);
        } elseif ($this->options['latest_brief'] ?? false) {
            $args['--latest-brief'] = true;
        }
        if ($this->options['instruction'] ?? '') {
            $args['--instruction'] = $this->options['instruction'];
        }
        Artisan::call('content:plan', $args);
        $this->mail('Claude drafted '.((int) $this->options['posts'] === 1 ? 'a post' : 'posts')." for {$this->client->name}", Artisan::output());
    }

    public function failed(?Throwable $e): void
    {
        $this->mail("Planning for {$this->client->name} failed", $e?->getMessage() ?? 'Unknown error');
    }

    private function mail(string $subject, string $body): void
    {
        $calendar = route('calendar', ['client' => $this->client->slug, 'date' => $this->options['from']]);
        Mail::raw("{$body}\n\nCalendar: {$calendar}", fn ($message) => $message->to($this->user->email, $this->user->name)->subject($subject));
        Slack::send("{$subject}\n".last(array_filter(explode("\n", trim($body))))."\n{$calendar}"); // the last line is the outcome or the error
    }
}
