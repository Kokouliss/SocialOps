<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/** One webhook delivery: JSON body, X-SocialOps-Signature = HMAC-SHA256 of the body with the hook's secret; retried twice over the next ten minutes. */
class SendWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 600];

    public function __construct(public string $url, public string $secret, public array $payload) {}

    public function handle(): void
    {
        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        Http::timeout(10)
            ->withHeaders(['X-SocialOps-Signature' => hash_hmac('sha256', $body, $this->secret), 'X-SocialOps-Event' => $this->payload['event']])
            ->withBody($body, 'application/json')
            ->post($this->url)
            ->throw();
    }
}
