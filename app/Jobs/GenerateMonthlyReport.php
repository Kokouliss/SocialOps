<?php

namespace App\Jobs;

use App\Alerts\Slack;
use App\Models\Client;
use App\Reporting\MonthlyReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Builds one client's monthly report on the queue and tells Slack where it is. */
class GenerateMonthlyReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public Client $client, public string $month) {}

    public function handle(MonthlyReport $reports): void
    {
        $report = $reports->generate($this->client, CarbonImmutable::parse($this->month.'-01'));
        Slack::send("{$this->client->name}: {$report->title} is ready\n".route('reports.show', $report));
    }

    public function failed(?Throwable $e): void
    {
        Slack::send("{$this->client->name}: the monthly report for {$this->month} failed: ".($e?->getMessage() ?? 'unknown error'));
    }
}
