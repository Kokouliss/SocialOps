<?php

namespace App\Console\Commands;

use App\Jobs\PublishPost;
use App\Models\Post;
use App\Posts\PostState;
use Illuminate\Console\Command;

/** Runs every minute: queues a publish job for each Scheduled post whose time has come. */
class PublishDuePosts extends Command
{
    protected $signature = 'posts:publish-due';

    protected $description = 'Queue the publish job for every scheduled post whose time has come';

    public function handle(): int
    {
        Post::where('state', PostState::Scheduled)->where('scheduled_at', '<=', now())
            ->each(fn (Post $post) => PublishPost::dispatch($post));

        return self::SUCCESS;
    }
}
