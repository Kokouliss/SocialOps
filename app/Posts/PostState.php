<?php

namespace App\Posts;

/**
 * Idea → Draft → In Review → Changes Requested → Approved (locked) → Scheduled → Published | Failed | Manual-Needed.
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

    private const TRANSITIONS = [
        'idea' => ['draft'],
        'draft' => ['in_review', 'idea'],
        'in_review' => ['changes_requested', 'approved', 'draft'],
        'changes_requested' => ['in_review', 'draft'],
        'approved' => ['scheduled', 'in_review'],
        'scheduled' => ['published', 'failed', 'manual_needed', 'in_review'],
        'published' => [],
        'failed' => ['scheduled', 'in_review'],
        'manual_needed' => ['published', 'in_review'],
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
        return in_array($this, [self::Approved, self::Scheduled, self::Published, self::Failed, self::ManualNeeded], true);
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
        };
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
        };
    }
}
