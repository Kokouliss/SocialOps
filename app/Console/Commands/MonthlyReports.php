<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyReport;
use App\Models\Client;
use Illuminate\Console\Command;

class MonthlyReports extends Command
{
    protected $signature = 'reports:monthly {client? : A client slug; default every client with posts in that month} {--month= : YYYY-MM, default last month}';

    protected $description = 'Queue the monthly report (numbers plus Claude\'s reading) for one client or all of them';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->subMonth()->format('Y-m');
        $clients = $this->argument('client')
            ? Client::where('slug', $this->argument('client'))->get()
            : Client::whereHas('posts', fn ($q) => $q->whereBetween('scheduled_at', [$month.'-01 00:00:00', now()->parse($month.'-01')->endOfMonth()]))->get();
        foreach ($clients as $client) {
            GenerateMonthlyReport::dispatch($client, $month);
            $this->info("{$client->name}: report for {$month} queued.");
        }

        return self::SUCCESS;
    }
}
