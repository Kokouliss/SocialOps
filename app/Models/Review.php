<?php

namespace App\Models;

use App\Alerts\Slack;
use App\Mail\Outbox;
use App\Mail\ReviewMail;
use App\Posts\PostState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/** A batch of posts sent to a client's approvers; the signed link is their only credential. */
#[Fillable(['client_id', 'due_at', 'created_by'])]
class Review extends Model
{
    public const LINK_DAYS = 30;

    public const PIVOT = ['decision', 'comment', 'decided_by', 'decided_at'];

    /** Why the request e-mail did not go out, when it did not (not stored: the review and the link exist either way). */
    public ?string $mailError = null;

    /** The client's profile has no approver, so no e-mail went out and the staff send the link themselves (not stored). */
    public bool $noApprover = false;

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'reminded_at' => 'datetime', 'escalated_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withPivot(self::PIVOT)->orderBy('posts.scheduled_at')->orderBy('posts.id');
    }

    public function pending(): BelongsToMany
    {
        return $this->posts()->wherePivotNull('decision');
    }

    /** The same link for the request mail and every reminder: it expires LINK_DAYS after the review was sent. */
    public function url(): string
    {
        return URL::temporarySignedRoute('portal.show', $this->linkExpiresAt(), ['review' => $this->id]);
    }

    public function assetUrl(Asset $asset): string
    {
        return URL::temporarySignedRoute('portal.asset', $this->linkExpiresAt(), ['review' => $this->id, 'asset' => $asset->id]);
    }

    public function linkExpiresAt(): Carbon
    {
        return $this->created_at->copy()->addDays(self::LINK_DAYS);
    }

    /** @return list<string> approver e-mails from the client's brand profile */
    public function approverEmails(): array
    {
        return array_column($this->client->profile['approval']['approvers'], 'email');
    }

    /** Record the approver's decision, move the post, and close the review once every post has one. */
    public function decide(Post $post, string $decision, ?string $comment, string $by): void
    {
        $this->posts()->updateExistingPivot($post->id, ['decision' => $decision, 'comment' => $comment, 'decided_by' => $by, 'decided_at' => now()]);

        // a post the staff already moved on (approved themselves, back to draft) keeps its state; the decision is still logged
        if ($post->state === PostState::InReview) {
            $post->transitionTo($decision === 'approved' ? PostState::Approved : PostState::ChangesRequested);
        }

        if ($this->pending()->doesntExist()) {
            $this->forceFill(['completed_at' => now()])->save();
            // the client's answers are saved by now: a mail server problem is logged, never shown to the client as an error page
            $mail = new ReviewMail($this, 'completed');
            Outbox::send($this->creator?->email, $mail);
            Slack::mailed($mail, route('clients.reviews', $this->client_id));
        }
    }
}
