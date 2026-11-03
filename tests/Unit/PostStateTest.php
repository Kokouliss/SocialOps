<?php

use App\Posts\PostState;

test('the state machine follows the brief', function () {
    expect(PostState::Idea->canTransitionTo(PostState::Draft))->toBeTrue()
        ->and(PostState::Idea->canTransitionTo(PostState::Approved))->toBeFalse()
        ->and(PostState::Draft->canTransitionTo(PostState::InReview))->toBeTrue()
        ->and(PostState::InReview->canTransitionTo(PostState::Approved))->toBeTrue()
        ->and(PostState::InReview->canTransitionTo(PostState::ChangesRequested))->toBeTrue()
        ->and(PostState::Approved->canTransitionTo(PostState::Scheduled))->toBeTrue()
        ->and(PostState::Scheduled->canTransitionTo(PostState::Published))->toBeTrue()
        ->and(PostState::Scheduled->canTransitionTo(PostState::ManualNeeded))->toBeTrue()
        ->and(PostState::Published->next())->toBe([])
        ->and(PostState::Draft->isLocked())->toBeFalse()
        ->and(PostState::Approved->isLocked())->toBeTrue();
});
