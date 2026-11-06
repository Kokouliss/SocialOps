<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a mail without letting a mail server problem break the page or the job that sends it: the failure is logged
 * and its message returned, so the caller can show it. No address, no mail (a client without approvers is allowed).
 */
class Outbox
{
    /**
     * @param  string|list<string>|null  $to
     * @return string|null the error message when the mail could not be sent, else null
     */
    public static function send(string|array|null $to, Mailable $mail): ?string
    {
        $to = array_values(array_filter((array) $to));
        if (! $to) {
            return null;
        }

        try {
            Mail::to($to)->send($mail);
        } catch (Throwable $e) {
            report($e);

            return $e->getMessage();
        }

        return null;
    }
}
