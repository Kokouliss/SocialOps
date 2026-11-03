<?php

namespace App\Console\Commands;

use App\Mail\TokenExpiring;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Runs daily: one warning per token that expires within ALERT_DAYS (or already has), with the re-connect link. */
class CheckTokens extends Command
{
    public const ALERT_DAYS = 10;

    protected $signature = 'accounts:check-tokens';

    protected $description = 'Warn the staff member who connected an account when its token is about to expire';

    public function handle(): int
    {
        $due = Account::with('client', 'connector')->whereNull('token_alerted_at')
            ->where('token_expires_at', '<=', now()->addDays(self::ALERT_DAYS))->get();

        foreach ($due as $account) {
            Mail::to($account->connector?->email ?? config('mail.from.address'))->send(new TokenExpiring($account));
            $account->forceFill(['token_alerted_at' => now()])->save();
            $this->info("{$account->client->name} {$account->platform}: token expires {$account->token_expires_at->diffForHumans()}, warned.");
        }

        return self::SUCCESS;
    }
}
