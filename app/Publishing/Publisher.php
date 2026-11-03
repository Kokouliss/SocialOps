<?php

namespace App\Publishing;

use App\Models\Account;
use App\Models\Post;

/** One driver per platform family, built on the official API behind our own app. */
interface Publisher
{
    /** Where the staff member is sent to authorise; $state comes back on the callback. */
    public function authorizeUrl(string $state): string;

    /**
     * Exchange the callback code for tokens and list every account they can publish to, unsaved.
     *
     * @return list<array{platform: string, external_id: string, name: string, access_token: string, refresh_token?: ?string, token_expires_at?: mixed, meta?: array}>
     */
    public function exchange(string $code): array;

    /**
     * Publish the post to this account and return the platform's id for it.
     *
     * @throws PublishFailed when the platform refused or is busy (the job retries, then marks the post Failed)
     * @throws ManualNeeded when no API can do it (a human posts in the app)
     */
    public function publish(Post $post, Account $account): string;

    /**
     * Take a published post down again.
     *
     * @throws PublishFailed when the platform refused
     * @throws ManualNeeded when the platform has no API for it (a human deletes it in the app)
     */
    public function withdraw(Account $account, string $externalId): void;
}
