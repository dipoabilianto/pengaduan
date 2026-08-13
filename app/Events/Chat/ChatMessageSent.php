<?php

namespace App\Events\Chat;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ShouldBroadcastNow (not ShouldBroadcast) — QUEUE_CONNECTION=database in this app
 * polls, so a queued broadcast would inherit that latency and stop being realtime.
 * Dispatch happens synchronously, in-process, right when the message is finalized.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    /**
     * @return array<int,Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.'.$this->message->chat_ticket_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'sender_type' => $this->message->sender_type,
            'sender_name' => $this->senderDisplayName(),
            'body' => $this->message->body,
            'ai_generated' => $this->message->ai_generated,
            'cta_action' => $this->message->cta_action,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }

    private function senderDisplayName(): string
    {
        return match ($this->message->sender_type) {
            ChatMessage::SENDER_OFFICER => $this->message->sender?->name ?? 'Petugas ULP',
            ChatMessage::SENDER_AI => 'Adelia Natata Herbi',
            ChatMessage::SENDER_SYSTEM => 'Sistem',
            default => 'Anda',
        };
    }
}
