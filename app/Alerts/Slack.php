<?php

namespace App\Alerts;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Throwable;

/** One line to the agency's Slack channel through an incoming webhook, next to the staff emails. Silent when SLACK_WEBHOOK_URL is empty. */
final class Slack
{
    public static function send(string $text): void
    {
        if (! $url = config('services.slack.webhook_url')) {
            return;
        }
        try {
            Http::timeout(5)->post($url, ['text' => $text])->throw();
        } catch (Throwable $e) {
            report($e); // a failed alert must not stop publishing or the scheduler
        }
    }

    /** The staff mail's subject plus a link, so Slack and the inbox say the same thing. */
    public static function mailed(Mailable $mail, string $url): void
    {
        self::send($mail->envelope()->subject."\n".$url);
    }
}
