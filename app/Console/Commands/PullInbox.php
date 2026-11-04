<?php

namespace App\Console\Commands;

use App\Jobs\PullDriveFolder;
use App\Models\Account;
use Illuminate\Console\Command;

class PullInbox extends Command
{
    protected $signature = 'inbox:pull';

    protected $description = 'Queue a pull of every watched Google Drive folder into its client\'s inbox';

    public function handle(): int
    {
        foreach (Account::where('platform', 'drive')->get() as $account) {
            PullDriveFolder::dispatch($account);
            $this->info("{$account->name}: queued");
        }

        return self::SUCCESS;
    }
}
