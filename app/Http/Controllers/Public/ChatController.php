<?php

namespace App\Http\Controllers\Public;

use App\Events\Chat\ChatAssistantTyping;
use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ChatTicketUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\RateChatMessageRequest;
use App\Http\Requests\SendChatMessageRequest;
use App\Http\Requests\StartChatRequest;
use App\Jobs\Chat\AnswerChatMessageWithAiJob;
use App\Models\ChatMessage;
use App\Models\ChatRating;
use App\Models\ChatTicket;
use App\Models\Report;
use App\Services\AiSettingsService;
use App\Services\ContentModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * A closed ticket's history is hidden from the widget between 6-12h after
     * closing (revealable via the "Lihat Riwayat Chat Sebelumnya" button), then
     * dropped from the widget entirely past 12h (still fully intact in the DB for
     * the admin console — see admin/chat, unaffected by any of this).
     */
    private const HISTORY_HIDDEN_AFTER_HOURS = 6;

    private const HISTORY_GONE_AFTER_HOURS = 12;

    public function __construct(
        private readonly AiSettingsService $aiSettings,
        private readonly ContentModerationService $moderation,
    ) {
    }

    /**
     * One ticket per phone number, forever (ChatTicket::findOrStartFor) — resumes
     * the citizen's existing thread if they've chatted before, from any device,
     * as long as they enter the same phone number again.
     */
    public function start(StartChatRequest $request): JsonResponse
    {
        $ticket = ChatTicket::findOrStartFor($request->validated('phone'));

        if ($ticketNo = $request->validated('related_ticket_no')) {
            $report = Report::where('ticket_no', $ticketNo)->first();

            if ($report && $ticket->related_report_id !== $report->id) {
                $ticket->update(['related_report_id' => $report->id]);

                ChatMessage::create([
                    'chat_ticket_id' => $ticket->id,
                    'sender_type' => ChatMessage::SENDER_SYSTEM,
                    'body' => "Pelanggan membuka chat terkait laporan {$report->ticket_no} ({$report->category}).",
                    'created_at' => now(),
                ]);
            }
        }

        $request->session()->put('chat_token_'.$ticket->id, $ticket->channel_token);

        [$messages, $historyHidden] = $this->visibleMessagesFor($ticket, $request);

        return response()->json([
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'history_hidden' => $historyHidden,
            'messages' => $messages,
            'awaiting_rating' => $this->awaitingRating($ticket),
        ]);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: bool}
     */
    private function visibleMessagesFor(ChatTicket $ticket, Request $request): array
    {
        $hoursSinceClosed = $ticket->status === ChatTicket::STATUS_SELESAI && $ticket->closed_at
            ? $ticket->closed_at->diffInHours(now())
            : null;

        $isGone = $hoursSinceClosed !== null && $hoursSinceClosed >= self::HISTORY_GONE_AFTER_HOURS;
        $isHidden = ! $isGone && $hoursSinceClosed !== null && $hoursSinceClosed >= self::HISTORY_HIDDEN_AFTER_HOURS
            && ! $request->boolean('reveal_history');

        if ($isGone || $isHidden) {
            return [collect(), $isHidden];
        }

        $messages = $ticket->messages->map(fn (ChatMessage $message) => [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'body' => $message->body,
            'cta_action' => $message->cta_action,
            'created_at' => $message->created_at->toIso8601String(),
        ]);

        return [$messages, false];
    }

    /**
     * The rating card is offered as long as the ticket's closed and its history hasn't
     * fully dropped from the widget yet (past HISTORY_GONE_AFTER_HOURS the conversation
     * is already gone from the citizen's view, so asking to rate it would feel
     * disconnected) — deliberately still offered during the "hidden" 6-12h window since
     * that's a lighter-weight action than revealing the full history.
     */
    private function awaitingRating(ChatTicket $ticket): bool
    {
        if (! $ticket->isClosed()) {
            return false;
        }

        if ($ticket->closed_at && $ticket->closed_at->diffInHours(now()) >= self::HISTORY_GONE_AFTER_HOURS) {
            return false;
        }

        return ! $this->hasBeenRated($ticket);
    }

    private function hasBeenRated(ChatTicket $ticket): bool
    {
        return $ticket->ratings()
            ->when($ticket->closed_at, fn ($query) => $query->where('created_at', '>=', $ticket->closed_at))
            ->exists();
    }

    public function rate(RateChatMessageRequest $request, ChatTicket $chatTicket): JsonResponse
    {
        if (! $chatTicket->isClosed()) {
            return response()->json(['message' => 'Chat belum ditandai selesai.'], 422);
        }

        // Idempotent rather than a hard error — a double-click/retry must never create a
        // second row for the same closure.
        if ($this->hasBeenRated($chatTicket)) {
            return response()->json(['status' => 'already_rated']);
        }

        ChatRating::create([
            'chat_ticket_id' => $chatTicket->id,
            'scale' => $request->validated('scale'),
            'comment' => $request->validated('comment'),
            'created_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function sendMessage(SendChatMessageRequest $request, ChatTicket $chatTicket): JsonResponse
    {
        $chatTicket->reopen();

        $message = ChatMessage::create([
            'chat_ticket_id' => $chatTicket->id,
            'sender_type' => ChatMessage::SENDER_CITIZEN,
            'body' => $request->validated('message'),
            'created_at' => now(),
        ]);

        // Content moderation is a deterministic keyword check (ContentModerationService),
        // not an AI judgment call — it must still catch flagged messages when AI is off/
        // unconfigured. The message is never rejected/hidden here, only marked so
        // AnswerChatMessageWithAiJob can react (fixed narrative reply, bypassing the AI
        // call) and so it surfaces in the officer inbox either way.
        $moderation = $this->moderation->check($message->body);

        if ($moderation['flagged']) {
            $message->update(['moderation_flagged' => true, 'escalation_flag' => true]);
        }

        $chatTicket->recordIncoming($message->body);
        $chatTicket->recordCitizenActivity();

        broadcast(new ChatMessageSent($message));
        broadcast(new ChatTicketUpdated($chatTicket->fresh()));

        // Queued (not sync) — the citizen's request returns immediately, the AI's
        // answer (1-3s external call) streams in later via broadcast. The job
        // re-checks ai_enabled/AI availability itself; this check just avoids
        // dispatching a job that would only immediately no-op.
        if ($chatTicket->ai_enabled && $this->aiSettings->isConfigured()) {
            // Reuses the citizen's existing "Petugas sedang mengetik…" indicator rather
            // than a separate "AI sedang menjawab" one — the citizen doesn't need to know
            // whether AI or a human is about to answer. It clears itself the same way
            // (3s silence, or immediately once the real reply/escalation arrives).
            broadcast(new ChatAssistantTyping($chatTicket->id));
            AnswerChatMessageWithAiJob::dispatch($message);
        }

        return response()->json(['status' => 'ok']);
    }
}
