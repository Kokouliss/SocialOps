<?php

namespace App\Jobs;

use App\Alerts\Slack;
use App\Drafting\Reply;
use App\Models\Client;
use App\Reporting\ListeningReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Builds one client's weekly listening report on the queue and tells Slack where it is. */
class GenerateListeningReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Client $client) {}

    public function handle(ListeningReport $reports): void
    {
        $report = $reports->generate($this->client);
        Slack::send("{$this->client->name}: {$report->title} is ready\n".route('reports.show', $report));
    }

    public function failed(?Throwable $e): void
    {
        Slack::send("{$this->client->name}: the listening report failed: ".Reply::error($e));
    }
}
