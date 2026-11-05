<?php

namespace App\Mail;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TokenExpiring extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Account $account)
    {
        $this->locale($account->connector?->locale ?? config('app.locale'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':client: reconnect :platform by :date', [
            'client' => $this->account->client->name,
            'platform' => str_replace('_', ' ', $this->account->platform),
            'date' => $this->account->token_expires_at->format('d-m-Y'),
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.token-expiring');
    }
}
