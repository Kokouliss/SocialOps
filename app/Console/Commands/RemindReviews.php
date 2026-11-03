<?php

namespace App\Console\Commands;

use App\Alerts\Slack;
use App\Mail\ReviewMail;
use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Runs hourly: reminder to the approvers at half the SLA, escalation to the agency when overdue, auto-approval where the client allows it. */
class RemindReviews extends Command
{
    protected $signature = 'reviews:remind';

    protected $description = 'Remind approvers of open reviews, escalate overdue ones and auto-approve where the client allows it';

    public function handle(): int
    {
        foreach (Review::whereNull('completed_at')->with('client', 'creator')->get() as $review) {
            if (now()->lt($review->due_at)) {
                if (! $review->reminded_at && now()->gte($review->created_at->copy()->average($review->due_at))) {
                    Mail::to($review->approverEmails())->send(new ReviewMail($review, 'reminder'));
                    $review->forceFill(['reminded_at' => now()])->save();
                }
            } elseif ($review->client->profile['approval']['auto_approve_on_timeout']) { // ClientProfile only allows this for the optional policy
                $review->pending()->get()->each(fn ($post) => $review->decide($post, 'approved', null, 'auto'));
                $this->info("Auto-approved review {$review->id} for {$review->client->name}.");
            } elseif (! $review->escalated_at) {
                $mail = new ReviewMail($review, 'overdue');
                if ($review->creator) {
                    Mail::to($review->creator->email)->send($mail);
                }
                Slack::mailed($mail, route('clients.reviews', $review->client_id));
                $review->forceFill(['escalated_at' => now()])->save();
            }
        }

        return self::SUCCESS;
    }
}
