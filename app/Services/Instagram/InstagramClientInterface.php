<?php

namespace App\Services\Instagram;

interface InstagramClientInterface
{
    /**
     * @throws \RuntimeException on API/network/parse failure — caller decides how to degrade.
     */
    public function sendDirectMessage(string $recipientId, string $message): void;

    /**
     * @throws \RuntimeException on API/network/parse failure — caller decides how to degrade.
     */
    public function replyToComment(string $commentId, string $message): void;

    /**
     * Mention webhooks only carry a comment_id, never the comment's text — this fetches it.
     * Returns null on any failure (unconfigured, API error, no text present); callers must
     * treat that as "couldn't fetch" and fall back to a placeholder, never throw from a
     * webhook handler over this.
     */
    public function fetchCommentText(string $commentId): ?string;
}
