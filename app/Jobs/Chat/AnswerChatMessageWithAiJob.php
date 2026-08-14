<?php

namespace App\Jobs\Chat;

use App\Events\Chat\ChatAssistantTyping;
use App\Events\Chat\ChatTicketUpdated;
use App\Models\ChatMessage;
use App\Models\ChatTicket;
use App\Services\Ai\AiClientFactory;
use App\Services\Ai\AiClientInterface;
use App\Services\AiSettingsService;
use App\Services\ChatAdminService;
use App\Services\ChatFactsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Mirrors ScoreReportUrgencyJob's defensive shape: AI auto-answer is best-effort — if
 * it's off, declines, fails, or the ticket's rate limit is spent, the citizen's message
 * must never sit invisibly stuck. It always ends up either answered or escalated
 * (escalation_flag set + ChatTicketUpdated broadcast so the inbox surfaces it), never
 * silently dropped.
 */
class AnswerChatMessageWithAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly ChatMessage $triggeringMessage)
    {
    }

    public function handle(AiClientFactory $factory, AiSettingsService $aiSettings, ChatFactsService $facts, ChatAdminService $chatAdmin): void
    {
        $ticket = $this->triggeringMessage->ticket()->first();

        // Re-check ai_enabled here, not just at dispatch time — an officer may have
        // claimed/replied (which flips it off) while this job sat in the queue.
        if (! $ticket || ! $ticket->ai_enabled) {
            return;
        }

        // If the citizen already sent a newer message by the time this job actually runs
        // (e.g. two messages a couple seconds apart), answering this stale one would just
        // duplicate whatever the newer message's own job produces — that job already
        // re-fetches full history and targets the true latest message. Bail out early
        // rather than waste an AI call on a question that's about to be superseded.
        if ($this->supersededByNewerMessage($ticket)) {
            return;
        }

        // Deterministic, not left to the model: a bug earlier proved prompt wording alone
        // isn't reliable for something that must be worded exactly right every time. The
        // triggering message is already flagged+escalated by ChatController::sendMessage()
        // before this job ever runs — this just guarantees the citizen-facing reply.
        if ($this->triggeringMessage->moderation_flagged) {
            $this->postModerationReply($ticket);

            return;
        }

        $client = $factory->make();

        if (! $client) {
            return;
        }

        // Capacity protection, not a user-facing error — treat exactly like needs_human.
        $withinLimit = RateLimiter::attempt('chat-ai.'.$ticket->id, 30, fn () => true, 3600);

        if (! $withinLimit) {
            $this->escalate();

            return;
        }

        try {
            $result = $this->callAi($client, $ticket, $facts, $aiSettings);

            if ($result['needs_human'] || ! $result['message']) {
                // Per the prompt, "needs_human" is reserved for the deadlock case ONLY:
                // escalation was already offered, the citizen didn't take it, AI is still
                // stuck. That's exactly the shape of a substantive complaint (petugas
                // conduct, pungli, etc.) — so point at the formal report form here rather
                // than leaving the citizen with nothing but "please wait".
                $this->escalate(suggestFormalComplaint: true);

                return;
            }

            // Deterministic backstop, not just a prompt instruction (same lesson as the
            // repeat-reply guard below): if the model already offered escalation recently
            // and tries to offer AGAIN, it failed to recognise its own deadlock — force a
            // real, silent handoff instead of looping the same offer on the citizen.
            if (($result['offer_escalation'] ?? false) && $this->alreadyOfferedRecently($ticket)) {
                Log::warning('AI tried to offer escalation again shortly after a recent offer, escalating instead.', [
                    'chat_ticket_id' => $ticket->id,
                ]);

                $this->escalate(suggestFormalComplaint: true);

                return;
            }

            // Hard guard, not just a prompt instruction: small/cheap models (e.g. Gemini
            // Flash Lite) can get anchored on their own recent replies and start echoing a
            // near-identical answer regardless of the new question — observed in production
            // as byte-identical replies to two differently-worded questions. Prompt wording
            // alone isn't reliable against this, so verify the actual output before it's
            // ever shown to the citizen.
            if ($this->repeatsRecentReply($ticket, $result['message'])) {
                Log::warning('AI chat reply looked like a stale repeat of a recent answer, escalating instead of posting.', [
                    'chat_ticket_id' => $ticket->id,
                ]);

                $this->escalate();

                return;
            }

            // Authoritative re-check, closing the race the early check above only reduces:
            // a newer citizen message can arrive WHILE the AI call above was in flight (it
            // can take several seconds). If so, this answer is already stale — drop it
            // silently rather than post a second near-duplicate reply once its own delayed
            // PostChatAiReplyJob fires. No escalation either: the newer message's job owns
            // responding to the conversation now.
            if ($this->supersededByNewerMessage($ticket)) {
                return;
            }

            $aiSettings->recordSuccess('chat');

            // Citizen just agreed to be redirected to file a formal complaint — worth
            // surfacing in the officer inbox as an audit trail, even though (unlike a hard
            // escalation) no "please wait" system notice is needed: the AI's own message
            // plus the report-form button already gives the citizen a clear next step.
            if (($result['cta'] ?? null) === 'report_form') {
                $this->triggeringMessage->update(['escalation_flag' => true]);
            }

            // Re-broadcast "typing" so the citizen's indicator clock resets right as the
            // simulated typing delay begins — the AI call itself may already have used a
            // few seconds. The actual message is posted by a SEPARATE, delayed job rather
            // than right here, so a long reply doesn't appear implausibly instantly after
            // a barely-there typing indicator (a dead giveaway it's not a human typing).
            broadcast(new ChatAssistantTyping($ticket->id));

            PostChatAiReplyJob::dispatch(
                $ticket->id,
                $result['message'],
                $result['offer_escalation'] ?? false,
                $result['cta'] ?? null,
            )->delay(now()->addMilliseconds($this->typingDelayFor($result['message'])));

            // Citizen explicitly said they're done — close now rather than wait for the
            // 6h inactivity auto-close (ProcessInactiveChatTicketsCommand). Reuses the
            // exact same service method an officer's "Tandai Selesai" button calls.
            if ($result['citizen_confirmed_done'] ?? false) {
                $chatAdmin->updateStatus($ticket, ChatTicket::STATUS_SELESAI);
            }
        } catch (Throwable $e) {
            Log::warning('AI chat auto-answer failed, escalating to staff.', [
                'chat_ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            $aiSettings->recordFailureFrom($e, 'chat');
            $this->escalate();
        }
    }

    /**
     * A citizen is waiting on this call, so there's no time budget for a queue-level
     * retry-with-backoff (the next attempt wouldn't run until the following cron cycle,
     * up to ~1 minute away — worse than just escalating). Instead: one immediate inline
     * retry for a transient failure (provider 5xx, connection/timeout — see
     * AiSettingsService::isTransientFailure()) only, since that's the one class of error
     * where trying again right now has a real chance of succeeding. Anything else (bad
     * key, malformed response) propagates straight to handle()'s catch block as before.
     *
     * @return array{message: ?string, needs_human: bool, offer_escalation: bool, cta: ?string, citizen_confirmed_done: bool}
     */
    private function callAi(AiClientInterface $client, ChatTicket $ticket, ChatFactsService $facts, AiSettingsService $aiSettings): array
    {
        $context = $this->buildContext($ticket, $facts);

        try {
            return $client->answerChatMessage($context);
        } catch (Throwable $e) {
            if (! $aiSettings->isTransientFailure($e)) {
                throw $e;
            }

            Log::info('Transient AI error answering chat message, retrying once inline.', [
                'chat_ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            return $client->answerChatMessage($context);
        }
    }

    /**
     * Fixed narrative, not AI-generated — see the moderation_flagged check in handle().
     */
    private function postModerationReply(ChatTicket $ticket): void
    {
        $message = 'Mohon maaf, pesan Anda mengandung bahasa yang kurang pantas. Hal tersebut akan '
            .'saya lanjutkan ke pejabat yang berwenang, untuk penanganan lebih baik. Kalau ada '
            .'keluhan atau pertanyaan yang ingin disampaikan dengan bahasa yang sopan, kami tetap siap membantu.';

        broadcast(new ChatAssistantTyping($ticket->id));

        PostChatAiReplyJob::dispatch($ticket->id, $message)
            ->delay(now()->addMilliseconds($this->typingDelayFor($message)));
    }

    /**
     * Roughly simulates human typing speed (~40 words/min) so a long reply doesn't post
     * implausibly fast — clamped so short replies still feel responsive and long ones
     * don't make the citizen wait too long for something already fully composed.
     */
    private function typingDelayFor(string $text): int
    {
        $wordCount = max(1, count(array_filter(explode(' ', trim($text)))));
        $ms = (int) round($wordCount / 40 * 60000);

        return max(1200, min($ms, 6000));
    }

    /**
     * No fake AI-authored answer is created — but the citizen must still see SOMETHING
     * happen (previously: the typing indicator just vanished with total silence, which
     * read as "the bot is broken"). A one-time acknowledgment covers that, without
     * repeating on every message while a human hasn't replied yet. The officer inbox
     * surfaces the real signal (ChatTicketController::index()'s pending_escalation_count)
     * — this message is purely citizen-facing reassurance.
     *
     * Posted as Tata (SENDER_AI via PostChatAiReplyJob), not a bare "Sistem" notice — a
     * flatly-worded system message right at the exact moment a citizen needs reassurance
     * broke the "talking to a real person" illusion the rest of the app maintains.
     *
     * @param  bool  $suggestFormalComplaint  True only for the genuine deadlock case
     *                                        (needs_human from the model, or the offer-
     *                                        loop guard) — both mean escalation was
     *                                        already offered and the citizen is stuck on
     *                                        something substantive (petugas conduct,
     *                                        pungli, etc.), so point at the report form
     *                                        instead of leaving them with just "wait".
     *                                        Left false for purely technical fallbacks
     *                                        (rate limit, near-duplicate guard, API
     *                                        failure) that aren't necessarily about a
     *                                        complaint at all.
     */
    private function escalate(bool $suggestFormalComplaint = false): void
    {
        $this->triggeringMessage->update(['escalation_flag' => true]);

        $ticket = $this->triggeringMessage->ticket()->first();

        if (! $ticket) {
            return;
        }

        // Regression: a slow AI call (e.g. provider hanging near its own timeout) can
        // still be in flight after a human has already taken over — an officer replied
        // (which flips ai_enabled off) or the citizen sent a further message. Posting
        // the "please wait" notice at that point would land disconnected from what's
        // actually happening on screen, reading exactly like a confused/broken bot.
        // The escalation_flag update above still stands (harmless audit trail), but the
        // citizen-facing notice itself must not be posted once the situation is stale.
        if (! $ticket->ai_enabled || $this->supersededByNewerMessage($ticket)) {
            return;
        }

        $alreadyWaitingOnStaff = $ticket->messages()
            ->where('escalation_flag', true)
            ->where('id', '!=', $this->triggeringMessage->id)
            ->exists();

        if (! $alreadyWaitingOnStaff) {
            $body = $suggestFormalComplaint
                ? 'Baik, Kak. Untuk hal kayak gini, paling pas memang ditindaklanjuti lewat pengaduan resmi ya, biar benar-benar tercatat dan sampai ke petugas yang berwenang. Sambil itu petugas kami di sini juga bakal segera bantu.'
                : 'Baik, Kak, pesannya sudah Tata terima ya. Sebentar lagi rekan petugas yang bantu langsung supaya jawabannya lebih pas — mohon ditunggu sebentar ya.';

            broadcast(new ChatAssistantTyping($ticket->id));

            PostChatAiReplyJob::dispatch(
                $ticket->id,
                $body,
                false,
                $suggestFormalComplaint ? 'report_form' : null,
            )->delay(now()->addMilliseconds($this->typingDelayFor($body)));
        }

        broadcast(new ChatTicketUpdated($ticket->fresh()));
    }

    /**
     * True if an AI offer-to-escalate already appears among the ticket's last few
     * messages. Deliberately a small recent window (not the whole ticket's lifetime —
     * chat tickets live forever per phone number) so a genuinely new, unrelated issue
     * raised much later can still be offered escalation again; this only catches the
     * tight "citizen declined, AI immediately tries to offer again" loop.
     */
    private function alreadyOfferedRecently(ChatTicket $ticket): bool
    {
        // ChatTicket::messages() bakes in ->orderBy('created_at') for chronological display
        // — chaining ->latest() on top only ADDS a secondary sort key, it never replaces
        // the first one, so this silently returned the ticket's OLDEST 6 messages instead
        // of its newest 6. reorder() clears that inherited ordering first.
        return $ticket->messages()
            ->reorder('id', 'desc')
            ->limit(6)
            ->get()
            ->contains(fn (ChatMessage $m) => $m->sender_type === ChatMessage::SENDER_AI && $m->offer_escalation);
    }

    /**
     * True if the citizen already sent a message after $this->triggeringMessage — that
     * newer message's own job is responsible for answering the conversation now.
     */
    private function supersededByNewerMessage(ChatTicket $ticket): bool
    {
        return $ticket->messages()
            ->where('sender_type', ChatMessage::SENDER_CITIZEN)
            ->where('id', '>', $this->triggeringMessage->id)
            ->exists();
    }

    /**
     * True if $candidate is a near-duplicate of the ticket's own last AI-generated reply.
     * Deliberately compares against only the SINGLE immediately-preceding AI message (not
     * the whole history) — that's the exact failure signature observed (two different
     * questions, byte-identical answers), and comparing against just one prior message
     * keeps this cheap and avoids false positives against older, legitimately-similar
     * short answers (e.g. two separate citizens both asking office hours).
     */
    private function repeatsRecentReply(ChatTicket $ticket, string $candidate): bool
    {
        // Same ordering pitfall as alreadyOfferedRecently() above — reorder() is required
        // or this silently compares against the ticket's very FIRST AI message ever sent
        // instead of the most recent one, which almost never trips the similarity check.
        $lastAiMessage = $ticket->messages()
            ->where('sender_type', ChatMessage::SENDER_AI)
            ->where('id', '!=', $this->triggeringMessage->id)
            ->reorder('id', 'desc')
            ->first();

        if (! $lastAiMessage || ! $lastAiMessage->body) {
            return false;
        }

        similar_text(mb_strtolower($candidate), mb_strtolower($lastAiMessage->body), $percent);

        return $percent >= 80.0;
    }

    /**
     * @return array{facts: string, history: array<int, array{role: string, body: string}>, related_report: ?array{category: string, status_label: string}, now: string}
     */
    private function buildContext(ChatTicket $ticket, ChatFactsService $facts): array
    {
        // Same ordering pitfall as alreadyOfferedRecently()/repeatsRecentReply() — without
        // reorder(), this silently fed the AI the ticket's OLDEST 20 messages once a ticket
        // passed 20 messages, missing the actual triggering message and everything recent.
        // The AI would then be answering with zero visibility into what was just asked.
        $recent = $ticket->messages()
            ->reorder('created_at', 'desc')
            ->limit(20)
            ->get()
            ->sortBy('created_at')
            ->values();

        // Cap how many of the AI's OWN past turns are echoed back into its own context —
        // keeping only the last 2. A long tail of the assistant's own prior replies (e.g.
        // after a stretch of off-topic banter) acts as strong few-shot precedent that can
        // out-weigh the system prompt's instructions for a small/cheap model, causing it to
        // keep imitating its own recent tone/pattern instead of answering fresh. Citizen/
        // officer/system messages are never dropped — only the assistant's own repetition
        // is trimmed.
        $aiTurnsSeen = 0;
        $keepFromEnd = $recent->reverse()->filter(function (ChatMessage $message) use (&$aiTurnsSeen) {
            if ($message->sender_type !== ChatMessage::SENDER_AI) {
                return true;
            }

            return ++$aiTurnsSeen <= 2;
        })->reverse()->values();

        $history = $keepFromEnd
            ->map(fn (ChatMessage $message) => [
                'role' => $message->sender_type === ChatMessage::SENDER_CITIZEN ? 'citizen' : 'assistant',
                'body' => $message->body,
            ])
            ->values()
            ->all();

        $report = $ticket->relatedReport;

        return [
            'facts' => $facts->get(),
            'history' => $history,
            'related_report' => $report ? [
                'category' => $report->category,
                'status_label' => $report->statusLabel(),
            ] : null,
            // Real wall-clock time, given explicitly rather than left for the model to
            // guess — a stateless LLM call has no innate sense of "now", so a question
            // like "masih buka gak sekarang?" needs this to compare against FAKTA's
            // schedule instead of just reciting the schedule blindly.
            'now' => now()->translatedFormat('l, d F Y, H:i').' WIB',
        ];
    }
}
