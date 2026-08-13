<?php

namespace App\Events\Chat;

use App\Models\ChatTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Drives the live-updating officer inbox list (new ticket, new message preview,
 * claimed, closed, AI-escalated) — separate from ChatMessageSent, which is
 * scoped to one ticket's own thread.
 */
class ChatTicketUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatTicket $ticket)
    {
    }

    /**
     * @return array<int,Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat-inbox')];
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticket->id,
            'status' => $this->ticket->status,
            'status_label' => $this->ticket->statusLabel(),
            'last_message_preview' => $this->ticket->last_message_preview,
            'last_message_at' => $this->ticket->last_message_at?->toIso8601String(),
            'assigned_officer' => $this->ticket->assignedOfficer?->name,
        ];
    }
}
