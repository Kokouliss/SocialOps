<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Someone opened an expired or incomplete client link and pressed "Ask for a new link" (`App\Http\ExpiredLink`). */
class LinkRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client, public string $kind, public string $path, public string $staffUrl, ?string $locale = null)
    {
        $this->locale($locale ?? config('app.locale'));
    }

    /** The subject, also the Slack line (there in the app's own language, `$locale`). */
    public static function title(string $kind, string $client, ?string $locale = null): string
    {
        return match ($kind) {
            'review' => __(':client: a new approval link was asked for', ['client' => $client], $locale),
            'inbox' => __(':client: a new upload link was asked for', ['client' => $client], $locale),
            'calendar' => __(':client: a new calendar link was asked for', ['client' => $client], $locale),
            'report' => __(':client: a new report link was asked for', ['client' => $client], $locale),
            'connect' => __(':client: a new link to connect the accounts was asked for', ['client' => $client], $locale),
        };
    }

    /** What the colleague does to send a new link of this kind. */
    public static function hint(string $kind): string
    {
        return match ($kind) {
            'review' => __('Send the posts for approval again on the Approvals page: the new review comes with a new link for the approvers.'),
            'inbox' => __('Copy the upload link on the Assets page and send it to the client.'),
            'calendar' => __('On the calendar with this client selected, press "Copy link for the client" and send it.'),
            'report' => __('Press "Email to client" on the report page: the approvers get a new link.'),
            'connect' => __('Copy the connect link on the Accounts page and send it to the client.'),
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::title($this->kind, $this->client->name));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.link-requested');
    }
}
