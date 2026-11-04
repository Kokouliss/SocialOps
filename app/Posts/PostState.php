<?php

namespace App\Posts;

/**
 * Idea → Draft → In Review → Changes Requested → Approved (locked) → Scheduled → Published | Failed | Manual-Needed, Published → Withdrawn.
 * Approved and later states are locked: editing content sends the post back to In Review.
 */
enum PostState: string
{
    case Idea = 'idea';
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';
    case ManualNeeded = 'manual_needed';
    case Withdrawn = 'withdrawn';

    private const TRANSITIONS = [
        'idea' => ['draft'],
        'draft' => ['in_review', 'idea'],
        'in_review' => ['changes_requested', 'approved', 'draft'],
        'changes_requested' => ['in_review', 'draft'],
        'approved' => ['scheduled', 'in_review'],
        'scheduled' => ['published', 'failed', 'manual_needed', 'in_review'],
        'published' => ['withdrawn'],
        'failed' => ['scheduled', 'in_review'],
        'manual_needed' => ['published', 'in_review'],
        'withdrawn' => ['in_review'],
    ];

    public function canTransitionTo(self $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$this->value], true);
    }

    /** @return list<self> */
    public function next(): array
    {
        return array_map(self::from(...), self::TRANSITIONS[$this->value]);
    }

    /** Locked content: changing it must go back through review. */
    public function isLocked(): bool
    {
        return in_array($this, [self::Approved, self::Scheduled, self::Published, self::Failed, self::ManualNeeded, self::Withdrawn], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Idea => 'Idea',
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::ChangesRequested => 'Changes requested',
            self::Approved => 'Approved',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Failed => 'Failed',
            self::ManualNeeded => 'Manual needed',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /** Tailwind classes of the badge, the same everywhere a state is shown (<x-state-badge>). */
    public function color(): string
    {
        return match ($this) {
            self::Idea, self::Draft => 'bg-gray-100 text-gray-700',
            self::InReview => 'bg-yellow-100 text-yellow-800',
            self::ChangesRequested => 'bg-orange-100 text-orange-800',
            self::Approved => 'bg-green-100 text-green-800',
            self::Scheduled => 'bg-blue-100 text-blue-800',
            self::Published => 'bg-green-200 text-green-900',
            self::Failed => 'bg-red-100 text-red-800',
            self::ManualNeeded => 'bg-purple-100 text-purple-800',
            self::Withdrawn => 'bg-gray-200 text-gray-500 line-through',
        };
    }

    /** States only the publishing drivers put a post into (config/features.php): no buttons for them while posting is off. */
    public function needsPublishing(): bool
    {
        return in_array($this, [self::Failed, self::ManualNeeded, self::Withdrawn], true);
    }

    /** The button text for moving a post into this state. */
    public function action(): string
    {
        return match ($this) {
            self::Idea => 'Back to idea',
            self::Draft => 'Back to draft',
            self::InReview => 'Send for review',
            self::ChangesRequested => 'Request changes',
            self::Approved => 'Approve',
            self::Scheduled => 'Schedule',
            self::Published => 'Mark published',
            self::Failed => 'Mark failed',
            self::ManualNeeded => 'Needs manual posting',
            self::Withdrawn => 'Withdraw',
        };
    }
}
