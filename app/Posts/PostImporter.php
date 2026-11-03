<?php

namespace App\Posts;

use App\Models\Account;
use App\Models\Post;
use App\Publishing\MetaPublisher;
use Carbon\Carbon;

/** Posts the client published themselves in the Instagram or Facebook app, pulled in as Published posts so the calendar and the insights see them. */
final class PostImporter
{
    public function __construct(private MetaPublisher $meta) {}

    /** Imports the account's last 30 days once: a post whose id is already on a post here (ours or imported before) is skipped. Returns how many came in. */
    public function import(Account $account): int
    {
        $client = $account->client;
        $known = Post::where('client_id', $client->id)->whereNotNull('external_ids')->pluck('external_ids')->pluck($account->platform)->filter()->all();
        $imported = 0;
        foreach ($this->meta->recentPosts($account) as $remote) {
            if (in_array($remote['id'], $known, true)) {
                continue;
            }
            $at = Carbon::parse($remote['at']);
            $post = $client->posts()->forceCreate([
                'name' => PostNamer::name($client, [$account->platform], $remote['format'], null, $at),
                'state' => PostState::Published,
                'platforms' => [$account->platform],
                'format' => $remote['format'],
                'brief' => 'Published by the client in the app, imported.',
                'scheduled_at' => $at,
                'published_at' => $at,
                'external_ids' => [$account->platform => $remote['id']],
            ]);
            $post->variants()->create(['platform' => $account->platform, 'lang' => $client->profile['languages'][0], 'caption' => $remote['caption'], 'hashtags' => []]);
            $imported++;
        }

        return $imported;
    }
}
