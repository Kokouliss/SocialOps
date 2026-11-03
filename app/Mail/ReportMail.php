<?php

namespace App\Mail;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** A monthly report to the client's approvers: signed link to read it, the month's numbers as a CSV. */
class ReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Report $report, public string $url, public string $csv) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->report->client->name}: {$this->report->title}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.report');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->csv, Str::slug($this->report->title).'.csv')->withMime('text/csv')];
    }
}
