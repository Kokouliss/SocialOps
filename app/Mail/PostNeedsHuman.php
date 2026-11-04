<?php

namespace App\Mail;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Publishing stopped: either no API can do it (manual) or the platform refused after retries (failed). */
class PostNeedsHuman extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Post $post, public string $kind)
    {
        $this->locale($post->creator?->locale ?? config('app.locale'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __($this->kind === 'manual' ? ':client: post it by hand, :name' : ':client: publishing failed, :name', ['client' => $this->post->client->name, 'name' => $this->post->name]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.post-needs-human');
    }
}
