<?php

namespace App\Jobs\Chat;

use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ChatTicketUpdated;
use App\Models\ChatMessage;
use App\Models\ChatTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Split out from AnswerChatMessageWithAiJob and dispatched with a delay (see that job's
 * typingDelayFor()) so the queue worker isn't sat sleeping — it schedules this and moves
 * on. The delay itself is what makes a long AI reply not appear implausibly instantly
 * after a barely-there "typing" indicator, which read as obviously robotic.
 */
class PostChatAiReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $chatTicketId,
        public readonly string $body,
        public readonly bool $offerEscalation = false,
        public readonly ?string $ctaAction = null,
    ) {
    }

    public function handle(): void
    {
        $ticket = ChatTicket::find($this->chatTicketId);

        if (! $ticket || ! $ticket->ai_enabled) {
            return;
        }

        $message = ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_AI,
            'body' => $this->body,
            'ai_generated' => true,
            'offer_escalation' => $this->offerEscalation,
            'cta_action' => $this->ctaAction,
            'created_at' => now(),
        ]);

        $ticket->recordIncoming($message->body);
        broadcast(new ChatMessageSent($message));
        broadcast(new ChatTicketUpdated($ticket->fresh()));
    }
}
