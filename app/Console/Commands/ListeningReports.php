<?php

namespace App\Console\Commands;

use App\Jobs\GenerateListeningReport;
use App\Models\Client;
use App\Reporting\ListeningReport;
use Illuminate\Console\Command;

class ListeningReports extends Command
{
    protected $signature = 'listening:report {client? : A client slug; default every client with hashtags or competitors to watch}';

    protected $description = 'Queue the weekly listening report (watched hashtags and competitors on Instagram, read by Claude)';

    public function handle(): int
    {
        $clients = $this->argument('client') ? Client::where('slug', $this->argument('client'))->get() : Client::all()->filter(fn ($c) => ListeningReport::watches($c));
        foreach ($clients as $client) {
            GenerateListeningReport::dispatch($client);
            $this->info("{$client->name}: listening report queued.");
        }

        return self::SUCCESS;
    }
}
