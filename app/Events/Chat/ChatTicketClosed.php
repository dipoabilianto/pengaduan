<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Lets the citizen's OWN open widget react live (offer the satisfaction rating card)
 * the moment a ticket is marked selesai — whichever of the three closers triggered it
 * (citizen confirming done, 6h auto-close, or an officer's "Tandai Selesai" button, all
 * funnelled through ChatAdminService::updateStatus()). No payload needed beyond the
 * ticket id — the widget already has everything else it needs locally.
 */
class ChatTicketClosed implements ShouldBroadcastNow
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
        return 'ticket.closed';
    }
}
