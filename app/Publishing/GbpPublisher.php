<?php

namespace App\Publishing;

use App\Models\Account;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/** Google Business Profile local posts (update, event, offer) through our own Google Cloud project. */
class GbpPublisher implements Publisher
{
    public const SCOPE = 'https://www.googleapis.com/auth/business.manage';

    /** The button under a local post; every type but CALL needs a link. */
    public const CTAS = ['LEARN_MORE' => 'Learn more', 'BOOK' => 'Book', 'ORDER' => 'Order online', 'SHOP' => 'Buy', 'SIGN_UP' => 'Sign up', 'CALL' => 'Call now'];

    public function authorizeUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('oauth.callback', 'google'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchange(string $code): array
    {
        $token = $this->token(['code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => route('oauth.callback', 'google')]);
        $accounts = [];
        foreach ($this->get($token['access_token'], 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts')['accounts'] ?? [] as $account) {
            $locations = $this->get($token['access_token'], "https://mybusinessbusinessinformation.googleapis.com/v1/{$account['name']}/locations", ['readMask' => 'name,title'])['locations'] ?? [];
            foreach ($locations as $location) {
                $accounts[] = [
                    'platform' => 'google_business',
                    'external_id' => "{$account['name']}/{$location['name']}", // accounts/{a}/locations/{l}, the v4 local posts path
                    'name' => $location['title'],
                    'access_token' => $token['access_token'],
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'token_expires_at' => now()->addSeconds($token['expires_in']),
                ];
            }
        }

        return $accounts;
    }

    public function publish(Post $post, Account $account): string
    {
        $first = $post->assets->first();
        throw_if($first?->kind === 'video', new ManualNeeded('Google Business posts take photos only through the API. Post the video in the Business Profile app.'));

        $settings = $post->settings['gbp'] ?? [];
        $type = match ($post->format) {
            'gbp_event' => 'EVENT', 'gbp_offer' => 'OFFER', default => 'STANDARD'
        };
        $body = [
            'languageCode' => $post->client->profile['languages'][0] ?? 'el',
            'summary' => $post->captionFor('google_business'),
            'topicType' => $type,
        ];
        if ($first) {
            $body['media'] = [['mediaFormat' => 'PHOTO', 'sourceUrl' => $first->publicUrl()]];
        }
        $cta = $settings['cta'] ?? 'LEARN_MORE';
        if ($cta === 'CALL') {
            $body['callToAction'] = ['actionType' => 'CALL']; // Google dials the location's own number
        } elseif ($settings['link'] ?? null) {
            $body['callToAction'] = ['actionType' => $cta, 'url' => $post->client->tagLinks($settings['link'], 'google_business', $post->name)];
        }
        if ($type !== 'STANDARD') {
            // Google wants a title and dates for events and offers; the offer details are optional on top
            $body['event'] = ['title' => $settings['title'] ?? $post->brief, 'schedule' => ['startDate' => self::date($settings['start'] ?? $post->scheduled_at), 'endDate' => self::date($settings['end'] ?? $settings['start'] ?? $post->scheduled_at)]];
            if ($type === 'OFFER' && ($settings['coupon'] ?? null)) {
                $body['offer'] = ['couponCode' => $settings['coupon']];
            }
        }

        $response = Http::withToken($this->freshToken($account))->post("https://mybusiness.googleapis.com/v4/{$account->external_id}/localPosts", $body);
        throw_unless($response->successful(), new PublishFailed('Google Business: '.($response->json('error.message') ?? $response->body())));

        return $response->json('name');
    }

    /**
     * The location's reviews, newest first, up to 50. A review the venue already answered carries that reply.
     *
     * @return list<array{id: string, author: ?string, text: string, rating: int, posted_at: string, reply: ?string, replied_at: ?string}>
     */
    public function reviews(Account $account): array
    {
        $stars = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];
        $data = $this->get($this->freshToken($account), "https://mybusiness.googleapis.com/v4/{$account->external_id}/reviews", ['pageSize' => 50])['reviews'] ?? [];

        return array_map(fn ($r) => [
            'id' => $r['reviewId'],
            'author' => $r['reviewer']['displayName'] ?? null,
            'text' => $r['comment'] ?? '',
            'rating' => $stars[$r['starRating'] ?? ''] ?? 0,
            'posted_at' => $r['createTime'],
            'reply' => $r['reviewReply']['comment'] ?? null,
            'replied_at' => $r['reviewReply']['updateTime'] ?? null,
        ], $data);
    }

    /** The venue's public reply to a review (a second call replaces the first). */
    public function reply(Account $account, string $reviewId, string $text): void
    {
        $response = Http::withToken($this->freshToken($account))->put("https://mybusiness.googleapis.com/v4/{$account->external_id}/reviews/{$reviewId}/reply", ['comment' => $text]);
        throw_unless($response->successful(), new PublishFailed('Google Business: '.($response->json('error.message') ?? $response->body())));
    }

    public function verify(Account $account): void
    {
        $this->get($this->freshToken($account), "https://mybusiness.googleapis.com/v4/{$account->external_id}/localPosts", ['pageSize' => 1]);
    }

    /** Google answers 200 before it reviews a post: LIVE, PROCESSING or REJECTED comes later. */
    public function state(Account $account, string $name): string
    {
        return $this->get($this->freshToken($account), "https://mybusiness.googleapis.com/v4/{$name}")['state'] ?? 'PROCESSING';
    }

    public function withdraw(Account $account, string $externalId): void
    {
        $response = Http::withToken($this->freshToken($account))->delete("https://mybusiness.googleapis.com/v4/{$externalId}");
        throw_unless($response->successful(), new PublishFailed('Google Business: '.($response->json('error.message') ?? $response->body())));
    }

    private static function date(mixed $date): array
    {
        $d = Carbon::parse($date ?: now());

        return ['year' => (int) $d->format('Y'), 'month' => (int) $d->format('n'), 'day' => (int) $d->format('j')];
    }

    /** Access tokens last an hour; the refresh token until the user revokes it. */
    private function freshToken(Account $account): string
    {
        if ($account->token_expires_at?->isFuture()) {
            return $account->access_token;
        }
        $token = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
        $account->forceFill(['access_token' => $token['access_token'], 'token_expires_at' => now()->addSeconds($token['expires_in'])])->save();

        return $token['access_token'];
    }

    private function token(array $params): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', $params + ['client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret')]);
        throw_unless($response->successful(), new PublishFailed('Google login: '.($response->json('error_description') ?? $response->body())));

        return $response->json();
    }

    private function get(string $token, string $url, array $query = []): array
    {
        $response = Http::withToken($token)->get($url, $query);
        throw_unless($response->successful(), new PublishFailed('Google Business: '.($response->json('error.message') ?? $response->body())));

        return $response->json();
    }
}
