<?php

namespace App\Services;

use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ChatTicketClosed;
use App\Events\Chat\ChatTicketUpdated;
use App\Models\AuditLog;
use App\Models\ChatMessage;
use App\Models\ChatTicket;
use App\Models\User;
use InvalidArgumentException;

class ChatAdminService
{
    public function __construct(private readonly ContentModerationService $moderation)
    {
    }

    public function claim(ChatTicket $ticket, User $officer): ChatTicket
    {
        if (! $ticket->assigned_to) {
            $ticket->update(['assigned_to' => $officer->id]);
            broadcast(new ChatTicketUpdated($ticket->fresh()));
        }

        return $ticket->fresh();
    }

    /**
     * Auto-claims the ticket if unclaimed, and flips ai_enabled off — once a human
     * has stepped into a conversation, AI shouldn't silently jump back in on the
     * next citizen message and potentially contradict what was just said. An
     * officer can re-enable it manually via toggleAi().
     */
    public function sendMessage(ChatTicket $ticket, User $officer, string $body, ?string $rawDraft = null): ChatMessage
    {
        // Petugas mewakili instansi — standarnya lebih ketat dari sisi pelapor (yang
        // pesannya tetap terkirim+dibalas dengan narasi eskalasi, lihat
        // AnswerChatMessageWithAiJob::postModerationReply()). Balasan petugas yang
        // terdeteksi tidak pantas TIDAK PERNAH tersimpan/terkirim sama sekali.
        if ($this->moderation->check($body)['flagged']) {
            throw new InvalidArgumentException('Balasan mengandung kata/bahasa yang tidak pantas — mohon perbaiki sebelum dikirim.');
        }

        $message = ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_OFFICER,
            'sender_user_id' => $officer->id,
            'body' => $body,
            'raw_draft' => $rawDraft,
            'created_at' => now(),
        ]);

        $ticket->update([
            'assigned_to' => $ticket->assigned_to ?? $officer->id,
            'status' => ChatTicket::STATUS_DITANGANI,
            'ai_enabled' => false,
        ]);
        $ticket->recordIncoming($body);

        // An officer has now looked at this ticket — clear any pending "AI declined
        // to answer" flags so the inbox's needs-attention badge doesn't stay stuck.
        $ticket->messages()->where('escalation_flag', true)->update(['escalation_flag' => false]);

        broadcast(new ChatMessageSent($message));
        broadcast(new ChatTicketUpdated($ticket->fresh()));

        return $message;
    }

    public function updateStatus(ChatTicket $ticket, string $status): ChatTicket
    {
        if (! array_key_exists($status, ChatTicket::STATUS_LABELS)) {
            throw new InvalidArgumentException("Status \"{$status}\" tidak dikenali.");
        }

        $ticket->update([
            'status' => $status,
            'closed_at' => $status === ChatTicket::STATUS_SELESAI ? now() : null,
        ]);

        broadcast(new ChatTicketUpdated($ticket->fresh()));

        // Single funnel point for all three closers (citizen confirming done, 6h
        // auto-close, officer's "Tandai Selesai") — the citizen's own widget picks this
        // up live to offer the satisfaction rating card.
        if ($status === ChatTicket::STATUS_SELESAI) {
            broadcast(new ChatTicketClosed($ticket->id));
        }

        return $ticket->fresh();
    }

    public function toggleAi(ChatTicket $ticket, bool $enabled): ChatTicket
    {
        $ticket->update(['ai_enabled' => $enabled]);

        return $ticket->fresh();
    }

    /**
     * Chat isn't anonymous whistleblowing (see ChatTicketPolicy::viewPhone doc) —
     * still audit-logged like Report's reveal, just without a mandatory reason.
     *
     * @return array{name: ?string, phone: string}
     */
    public function revealPhone(ChatTicket $ticket, User $officer, ?string $ip): array
    {
        AuditLog::create([
            'user_id' => $officer->id,
            'action' => 'Buka nomor HP chat.',
            'subject_type' => ChatTicket::class,
            'subject_id' => $ticket->id,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);

        return ['name' => $ticket->name_enc, 'phone' => $ticket->phone_enc];
    }
}
