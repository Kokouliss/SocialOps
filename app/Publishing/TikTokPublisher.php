<?php

namespace App\Publishing;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * TikTok Content Posting API, Direct Post. Until our app passes TikTok's audit it may only post privately
 * (SELF_ONLY), so public posts go to Manual-Needed and a human posts them in the TikTok app.
 */
class TikTokPublisher implements Publisher
{
    public const API = 'https://open.tiktokapis.com/v2';

    public const SCOPES = 'user.info.basic,video.publish,video.list';

    public const PRIVACY = ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'];

    public function authorizeUrl(string $state): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => config('services.tiktok.client_key'),
            'scope' => self::SCOPES,
            'response_type' => 'code',
            'redirect_uri' => route('oauth.callback', 'tiktok'),
            'state' => $state,
        ]);
    }

    public function exchange(string $code): array
    {
        $token = $this->token(['code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => route('oauth.callback', 'tiktok')]);
        $creator = $this->creatorInfo($token['access_token']);

        return [[
            'platform' => 'tiktok',
            'external_id' => $token['open_id'],
            'name' => '@'.$creator['creator_username'],
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'token_expires_at' => now()->addSeconds($token['expires_in']),
            'meta' => $creator,
        ]];
    }

    public function publish(Post $post, Account $account): string
    {
        $settings = $post->settings['tiktok'] ?? [];
        $privacy = $settings['privacy_level'] ?? 'PUBLIC_TO_EVERYONE';
        if (! config('services.tiktok.audited') && $privacy !== 'SELF_ONLY') {
            throw new ManualNeeded('Our TikTok app is not audited yet, so it can only post privately. Post this one in the TikTok app.');
        }

        $token = $this->freshToken($account);
        $creator = $this->creatorInfo($token); // live options: what this creator may do right now
        throw_unless(in_array($privacy, $creator['privacy_level_options'] ?? [$privacy]), new PublishFailed("TikTok does not allow {$privacy} for {$account->name} right now"));

        $assets = $post->assets;
        throw_if($assets->isEmpty(), new PublishFailed('TikTok needs a video or photos'));
        $caption = $post->captionFor('tiktok');
        $commercial = $settings['commercial'] ?? 'none';
        $info = [
            'privacy_level' => $privacy,
            'disable_comment' => ! ($settings['allow_comment'] ?? true),
            'brand_content_toggle' => $commercial === 'branded_content',
            'brand_organic_toggle' => $commercial === 'your_brand',
        ];

        if ($assets[0]->kind === 'video') {
            $reply = $this->api($token, '/post/publish/video/init/', [
                'post_info' => $info + ['title' => $caption, 'disable_duet' => ! ($settings['allow_duet'] ?? true), 'disable_stitch' => ! ($settings['allow_stitch'] ?? true)],
                'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $assets[0]->publicUrl()],
            ]);
        } else {
            $reply = $this->api($token, '/post/publish/content/init/', [
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
                'post_info' => $info + ['title' => mb_substr(strtok($caption, "\n"), 0, 90), 'description' => mb_substr($caption, 0, 4000)],
                'source_info' => ['source' => 'PULL_FROM_URL', 'photo_cover_index' => 0, 'photo_images' => $assets->map(fn (Asset $a) => $a->publicUrl())->values()->all()],
            ]);
        }

        foreach (range(1, 30) as $try) {
            $status = $this->api($token, '/post/publish/status/fetch/', ['publish_id' => $reply['publish_id']]);
            if ($status['status'] === 'PUBLISH_COMPLETE') {
                return $status['publicaly_available_post_id'][0] ?? $reply['publish_id'];
            }
            throw_if($status['status'] === 'FAILED', new PublishFailed('TikTok rejected the post: '.($status['fail_reason'] ?? 'unknown reason')));
            Sleep::for(5)->seconds();
        }
        throw new PublishFailed('TikTok is still processing the post, retrying later');
    }

    /** Views, likes, comments and shares of one video through the Display API (scope video.list); a publish id is resolved to the video first. */
    public function insights(Account $account, string $id): array
    {
        $token = $this->freshToken($account);
        if (str_starts_with($id, 'v_')) {
            $id = $this->api($token, '/post/publish/status/fetch/', ['publish_id' => $id])['publicaly_available_post_id'][0] ?? throw new PublishFailed('TikTok: the post has no public id yet');
        }
        $video = $this->api($token, '/video/query/?fields=id,view_count,like_count,comment_count,share_count', ['filters' => ['video_ids' => [(string) $id]]])['videos'][0] ?? throw new PublishFailed("TikTok: video {$id} not found");

        return ['reach' => null, 'views' => $video['view_count'] ?? null, 'likes' => $video['like_count'] ?? null, 'comments' => $video['comment_count'] ?? null, 'shares' => $video['share_count'] ?? null, 'saved' => null];
    }

    /** Access tokens last 24 h; the refresh token a year. */
    public function verify(Account $account): void
    {
        $this->creatorInfo($this->freshToken($account));
    }

    public function withdraw(Account $account, string $externalId): void
    {
        throw new ManualNeeded('TikTok has no API to delete a video. Delete it in the TikTok app.');
    }

    private function freshToken(Account $account): string
    {
        if ($account->token_expires_at?->isFuture()) {
            return $account->access_token;
        }
        $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
        $account->forceFill(['access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'token_expires_at' => now()->addSeconds($token['expires_in'])])->save();

        return $token['access_token'];
    }

    private function token(array $params): array
    {
        $response = Http::asForm()->post(self::API.'/oauth/token/', $params + ['client_key' => config('services.tiktok.client_key'), 'client_secret' => config('services.tiktok.client_secret')]);
        throw_if(! $response->successful() || $response->json('error'), new PublishFailed('TikTok login: '.($response->json('error_description') ?? $response->body())));

        return $response->json();
    }

    private function creatorInfo(string $token): array
    {
        return $this->api($token, '/post/publish/creator_info/query/', []);
    }

    private function api(string $token, string $path, array $body): array
    {
        $response = Http::withToken($token)->post(self::API.$path, $body);
        $code = $response->json('error.code', $response->successful() ? 'ok' : 'http');
        throw_if($code !== 'ok', new PublishFailed("TikTok: {$code} ".($response->json('error.message') ?? $response->body())));

        return $response->json('data') ?? [];
    }
}
