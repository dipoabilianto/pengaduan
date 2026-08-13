<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Server-originated equivalent of the client "typing" whisper (see chat-widget.js /
 * admin-chat.js) — a queued job has no live socket to whisper from, so this is a real
 * broadcast event instead. Deliberately reuses the exact same citizen-facing "Petugas
 * sedang mengetik…" indicator/state rather than a separate "AI sedang menjawab" one —
 * the citizen doesn't need to know whether AI or a human is about to answer, and this
 * indicator naturally clears itself the same way (3s silence timeout, or immediately
 * once the real reply/escalation arrives via ChatMessageSent/ChatTicketUpdated).
 */
class ChatAssistantTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $chatTicketId)
    {
    }

    /**
     * @return array<int,Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.'.$this->chatTicketId)];
    }

    public function broadcastAs(): string
    {
        return 'assistant.typing';
    }
}
