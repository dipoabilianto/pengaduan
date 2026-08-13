<?php

namespace App\Services\Instagram;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Instagram Graph API resmi (Meta for Developers) — akun Instagram Professional yang
 * terhubung ke Facebook Page, bukan mitra pihak ketiga.
 */
class GraphApiClient implements InstagramClientInterface
{
    public function __construct(
        private readonly string $businessAccountId,
        private readonly string $accessToken,
    ) {
    }

    public function sendDirectMessage(string $recipientId, string $message): void
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->post("https://graph.facebook.com/v20.0/{$this->businessAccountId}/messages", [
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Instagram Graph API error: '.$response->status().' '.$response->body());
        }
    }

    public function replyToComment(string $commentId, string $message): void
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->post("https://graph.facebook.com/v20.0/{$commentId}/replies", [
                'message' => $message,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Instagram Graph API error: '.$response->status().' '.$response->body());
        }
    }

    public function fetchCommentText(string $commentId): ?string
    {
        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->get("https://graph.facebook.com/v20.0/{$this->businessAccountId}", [
                    'fields' => 'mentioned_comment{text}',
                    'comment_id' => $commentId,
                ]);

            if ($response->failed()) {
                return null;
            }

            $text = $response->json('mentioned_comment.text');

            return is_string($text) && trim($text) !== '' ? $text : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
