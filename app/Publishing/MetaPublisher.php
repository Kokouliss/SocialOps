<?php

namespace App\Publishing;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Post;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/** Instagram and Facebook Pages through the Graph API with our own Meta app. One authorisation covers both. */
class MetaPublisher implements Publisher
{
    public const SCOPES = 'pages_show_list,pages_manage_posts,pages_read_engagement,pages_read_user_content,pages_manage_engagement,instagram_basic,instagram_content_publish,instagram_manage_comments,instagram_manage_insights,business_management';

    public function authorizeUrl(string $state): string
    {
        return 'https://www.facebook.com/'.config('services.meta.graph_version').'/dialog/oauth?'.http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => route('oauth.callback', 'meta'),
            'state' => $state,
            'scope' => self::SCOPES,
        ]);
    }

    public function exchange(string $code): array
    {
        $app = ['client_id' => config('services.meta.app_id'), 'client_secret' => config('services.meta.app_secret')];
        $short = $this->get('/oauth/access_token', $app + ['redirect_uri' => route('oauth.callback', 'meta'), 'code' => $code]);
        $long = $this->get('/oauth/access_token', $app + ['grant_type' => 'fb_exchange_token', 'fb_exchange_token' => $short['access_token']]);
        $pages = $this->get('/me/accounts', ['fields' => 'id,name,access_token,instagram_business_account{id,username}', 'access_token' => $long['access_token']]);

        $accounts = [];
        foreach ($pages['data'] as $page) {
            // Page tokens issued from a long-lived user token do not expire by themselves; 60 days makes the monitor re-check anyway
            $token = ['access_token' => $page['access_token'], 'token_expires_at' => now()->addDays(60)];
            $accounts[] = ['platform' => 'facebook', 'external_id' => $page['id'], 'name' => $page['name']] + $token;
            if ($ig = $page['instagram_business_account'] ?? null) {
                $accounts[] = ['platform' => 'instagram', 'external_id' => $ig['id'], 'name' => '@'.$ig['username']] + $token;
            }
        }

        return $accounts;
    }

    public function publish(Post $post, Account $account): string
    {
        $caption = $post->captionFor($account->platform);

        return $account->platform === 'instagram' ? $this->publishInstagram($post, $account, $caption) : $this->publishFacebook($post, $account, $caption);
    }

    private function publishInstagram(Post $post, Account $account, string $caption): string
    {
        $token = ['access_token' => $account->access_token];
        $user = "/{$account->external_id}";

        // never a hard-coded quota: ask Instagram what is left in the rolling 24 h
        $limit = $this->get("$user/content_publishing_limit", $token + ['fields' => 'quota_usage,config'])['data'][0] ?? null;
        if ($limit && $limit['quota_usage'] >= $limit['config']['quota_total']) {
            throw new PublishFailed("Instagram publishing limit reached ({$limit['quota_usage']} of {$limit['config']['quota_total']} in 24 h), retrying later");
        }

        $assets = $post->assets;
        throw_if($assets->isEmpty(), new PublishFailed('Instagram needs a photo or video'));
        $single = $assets[0];
        $container = match (true) {
            $post->format === 'carousel' => $this->post("$user/media", $token + ['media_type' => 'CAROUSEL', 'caption' => $caption, 'children' => $assets->map(fn (Asset $a) => $this->post("$user/media", $token + self::source($a) + ['is_carousel_item' => 'true'])['id'])->join(',')]),
            $post->format === 'story' => $this->post("$user/media", $token + ['media_type' => 'STORIES'] + self::source($single, false)),
            $single->kind === 'video' => $this->post("$user/media", $token + ['media_type' => 'REELS', 'video_url' => $single->publicUrl(), 'caption' => $caption, 'share_to_feed' => $post->format === 'reel' ? 'true' : 'false']),
            default => $this->post("$user/media", $token + ['image_url' => $single->publicUrl(), 'caption' => $caption, 'alt_text' => $post->altTextFor('instagram')]),
        };

        // videos are processed asynchronously; publishing before FINISHED fails
        foreach (range(1, 30) as $try) {
            $status = $this->get("/{$container['id']}", $token + ['fields' => 'status_code,status'])['status_code'] ?? 'FINISHED';
            if ($status === 'FINISHED') {
                return $this->post("$user/media_publish", $token + ['creation_id' => $container['id']])['id'];
            }
            throw_if($status === 'ERROR', new PublishFailed('Instagram could not process the media: '.json_encode($status)));
            Sleep::for(5)->seconds();
        }
        throw new PublishFailed('Instagram is still processing the media, retrying later');
    }

    private static function source(Asset $asset, bool $carouselItem = true): array
    {
        return $asset->kind === 'video'
            ? ['media_type' => $carouselItem ? 'VIDEO' : 'STORIES', 'video_url' => $asset->publicUrl()]
            : ['image_url' => $asset->publicUrl()];
    }

    private function publishFacebook(Post $post, Account $account, string $caption): string
    {
        $token = ['access_token' => $account->access_token];
        $page = "/{$account->external_id}";
        $assets = $post->assets;
        $first = $assets->first();

        if ($first?->kind === 'video') {
            // Reels and video Stories share the resumable upload: start, hand Meta the file URL, finish
            $edge = $post->format === 'story' ? 'video_stories' : 'video_reels';
            $start = $this->post("$page/$edge", $token + ['upload_phase' => 'start']);
            $upload = Http::withHeaders(['Authorization' => 'OAuth '.$account->access_token, 'file_url' => $first->publicUrl()])
                ->post('https://rupload.facebook.com/video-upload/'.config('services.meta.graph_version').'/'.$start['video_id']);
            throw_unless($upload->successful(), new PublishFailed('Meta upload: '.$upload->body()));
            $finish = $this->post("$page/$edge", $token + ['upload_phase' => 'finish', 'video_id' => $start['video_id'], 'video_state' => 'PUBLISHED', 'description' => $caption]);

            return $finish['post_id'] ?? $start['video_id'];
        }
        if ($post->format === 'story') {
            throw_if(! $first, new PublishFailed('A Facebook story needs a photo or video'));
            $photo = $this->post("$page/photos", $token + ['url' => $first->publicUrl(), 'published' => 'false']);

            return $this->post("$page/photo_stories", $token + ['photo_id' => $photo['id']])['post_id'];
        }
        if (! $first) {
            return $this->post("$page/feed", $token + ['message' => $caption])['id'];
        }
        if ($assets->count() === 1) {
            return $this->post("$page/photos", $token + ['url' => $first->publicUrl(), 'message' => $caption, 'alt_text_custom' => $post->altTextFor('facebook')])['id'];
        }
        $media = $assets->map(fn (Asset $a) => ['media_fbid' => $this->post("$page/photos", $token + ['url' => $a->publicUrl(), 'published' => 'false'])['id']]);

        return $this->post("$page/feed", $token + ['message' => $caption, 'attached_media' => $media->values()->all()])['id'];
    }

    /**
     * Comments other people left under one published post, newest first, up to 50.
     *
     * @return list<array{id: string, text: string, author: ?string, posted_at: string}>
     */
    public function comments(Account $account, string $mediaId): array
    {
        $token = ['access_token' => $account->access_token];
        if ($account->platform === 'instagram') {
            $data = $this->get("/{$mediaId}/comments", $token + ['fields' => 'id,text,username,timestamp', 'limit' => 50])['data'] ?? [];
            $own = ltrim($account->name, '@');

            return array_values(array_map(fn ($c) => ['id' => $c['id'], 'text' => $c['text'] ?? '', 'author' => $c['username'] ?? null, 'posted_at' => $c['timestamp']],
                array_filter($data, fn ($c) => ($c['username'] ?? null) !== $own)));
        }
        $data = $this->get("/{$mediaId}/comments", $token + ['fields' => 'id,message,from,created_time', 'limit' => 50])['data'] ?? [];

        return array_values(array_map(fn ($c) => ['id' => $c['id'], 'text' => $c['message'] ?? '', 'author' => $c['from']['name'] ?? null, 'posted_at' => $c['created_time']],
            array_filter($data, fn ($c) => ($c['from']['id'] ?? null) !== $account->external_id)));
    }

    /**
     * Lifetime metrics of one published post. Reach and views come from the insights edge, which not every media type
     * supports, so they are null when Meta refuses; likes and comments always come from the post itself.
     *
     * @return array{reach: ?int, views: ?int, likes: ?int, comments: ?int, shares: ?int, saved: ?int}
     */
    public function insights(Account $account, string $mediaId): array
    {
        $token = ['access_token' => $account->access_token];
        $out = array_fill_keys(['reach', 'views', 'likes', 'comments', 'shares', 'saved'], null);
        if ($account->platform === 'instagram') {
            $media = $this->get("/{$mediaId}", $token + ['fields' => 'like_count,comments_count']);
            $out['likes'] = $media['like_count'] ?? null;
            $out['comments'] = $media['comments_count'] ?? null;
            $metrics = 'reach,views,shares,saved';
        } else {
            $post = $this->get("/{$mediaId}", $token + ['fields' => 'shares,likes.summary(true),comments.summary(true)']);
            $out['likes'] = $post['likes']['summary']['total_count'] ?? null;
            $out['comments'] = $post['comments']['summary']['total_count'] ?? null;
            $out['shares'] = $post['shares']['count'] ?? null;
            $metrics = 'post_impressions_unique';
        }
        try {
            foreach ($this->get("/{$mediaId}/insights", $token + ['metric' => $metrics])['data'] ?? [] as $row) {
                $out[$row['name'] === 'post_impressions_unique' ? 'reach' : $row['name']] = $row['values'][0]['value'] ?? null;
            }
        } catch (PublishFailed) {
            // insights are not offered for every media type; the counts above still stand
        }

        return $out;
    }

    /**
     * The top posts under a hashtag through the Hashtag Search API. Instagram allows 30 distinct hashtags per account per rolling week.
     *
     * @return list<array{id: string, caption?: string, like_count?: int, comments_count?: int, media_type?: string, permalink?: string, timestamp?: string}>
     */
    public function hashtagTopMedia(Account $account, string $tag, int $limit = 25): array
    {
        $token = ['access_token' => $account->access_token, 'user_id' => $account->external_id];
        $id = $this->get('/ig_hashtag_search', $token + ['q' => ltrim($tag, '#')])['data'][0]['id'] ?? throw new PublishFailed("Instagram knows no hashtag #{$tag}");

        return $this->get("/{$id}/top_media", $token + ['fields' => 'id,caption,like_count,comments_count,media_type,permalink,timestamp', 'limit' => $limit])['data'] ?? [];
    }

    /**
     * A public business or creator account's counts and recent posts through Business Discovery.
     *
     * @return array{username?: string, followers_count?: int, media_count?: int, media?: array{data: list<array>}}
     */
    public function businessDiscovery(Account $account, string $username, int $limit = 10): array
    {
        $username = ltrim($username, '@');
        $fields = "business_discovery.username({$username}){username,followers_count,media_count,media.limit({$limit}){caption,like_count,comments_count,media_type,permalink,timestamp}}";

        return $this->get("/{$account->external_id}", ['access_token' => $account->access_token, 'fields' => $fields])['business_discovery'] ?? [];
    }

    /** A comment under the client's own post (the first comment: hashtags or a link); returns its id. */
    public function comment(Account $account, string $mediaId, string $text): string
    {
        return $this->post("/{$mediaId}/comments", ['access_token' => $account->access_token, 'message' => $text])['id'];
    }

    /** Reply under someone's comment; returns the reply's id. */
    public function reply(Account $account, string $commentId, string $text): string
    {
        $edge = $account->platform === 'instagram' ? 'replies' : 'comments';

        return $this->post("/{$commentId}/{$edge}", ['access_token' => $account->access_token, 'message' => $text])['id'];
    }

    public function hide(Account $account, string $commentId): void
    {
        $field = $account->platform === 'instagram' ? 'hide' : 'is_hidden';
        $this->post("/{$commentId}", ['access_token' => $account->access_token, $field => 'true']);
    }

    private function get(string $path, array $query): array
    {
        return $this->check(Http::get($this->graph().$path, $query));
    }

    private function post(string $path, array $params): array
    {
        return $this->check(Http::asForm()->post($this->graph().$path, array_filter($params, fn ($v) => $v !== null && $v !== '')));
    }

    private function check(Response $response): array
    {
        throw_unless($response->successful(), new PublishFailed('Meta: '.($response->json('error.message') ?? $response->body())));

        return $response->json();
    }

    private function graph(): string
    {
        return 'https://graph.facebook.com/'.config('services.meta.graph_version');
    }
}
