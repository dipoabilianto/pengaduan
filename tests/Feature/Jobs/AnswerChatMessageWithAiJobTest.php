<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Chat\AnswerChatMessageWithAiJob;
use App\Jobs\Chat\PostChatAiReplyJob;
use App\Models\ChatMessage;
use App\Models\ChatTicket;
use App\Models\User;
use App\Services\Ai\AiClientFactory;
use App\Services\AiSettingsService;
use App\Services\ChatAdminService;
use App\Services\ChatFactsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AnswerChatMessageWithAiJobTest extends TestCase
{
    use RefreshDatabase;

    private function configureAi(): void
    {
        app(AiSettingsService::class)->save(
            ['provider' => 'gemini', 'api_key' => 'test-key', 'model' => 'gemini-flash-latest'],
            User::factory()->create(),
        );
    }

    private function citizenMessage(ChatTicket $ticket, string $body = 'Jam buka kantor jam berapa?'): ChatMessage
    {
        return ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_CITIZEN,
            'body' => $body,
            'created_at' => now(),
        ]);
    }

    private function fakeGeminiAnswer(array $payload): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($payload)]]]],
                ],
            ]),
        ]);
    }

    private function handleJob(AnswerChatMessageWithAiJob $job): void
    {
        $job->handle(app(AiClientFactory::class), app(AiSettingsService::class), app(ChatFactsService::class), app(ChatAdminService::class));
    }

    /**
     * The actual ChatMessage isn't created synchronously here anymore — a found
     * answer is posted via a separate, delayed job (simulated human-typing delay,
     * see PostChatAiReplyJob) so a long reply doesn't appear implausibly instantly.
     */
    public function test_ai_answering_a_general_question_schedules_a_delayed_reply(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket);
        $this->fakeGeminiAnswer(['needs_human' => false, 'message' => 'Kantor buka Senin-Jumat, 08.00-16.00 WIB.']);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => $job->chatTicketId === $ticket->id
            && $job->body === 'Kantor buka Senin-Jumat, 08.00-16.00 WIB.');
        $this->assertFalse($message->fresh()->escalation_flag);
    }

    /**
     * The model's own hallucinated "answer" text (message: null here, but the principle
     * holds generally) must never be saved as if it were a genuine reply — only the fixed,
     * Tata-voiced acknowledgment may be posted, and it's flagged for the report form since
     * "needs_human" is reserved for the deadlock/complaint case (see escalate() docblock).
     */
    public function test_ai_declining_escalates_without_creating_a_fake_ai_message(): void
    {
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket, 'Petugas A kemarin kasar sekali ke saya.');
        $this->fakeGeminiAnswer(['needs_human' => true, 'message' => null]);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        $notice = ChatMessage::where('sender_type', ChatMessage::SENDER_AI)->first();
        $this->assertNotNull($notice);
        $this->assertSame('report_form', $notice->cta_action);
        $this->assertTrue($message->fresh()->escalation_flag);
    }

    /**
     * Previously the citizen saw the typing indicator vanish into total silence when AI
     * declined — looked exactly like the bot was broken. A one-time acknowledgment fixes
     * that ("something happens" instead of nothing) — posted AS Tata (SENDER_AI), not a
     * bare "Sistem" notice, so the moment a citizen most needs reassurance doesn't read
     * like a robotic system message.
     */
    public function test_ai_declining_sends_a_citizen_facing_acknowledgment(): void
    {
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket, 'halo');
        $this->fakeGeminiAnswer(['needs_human' => true, 'message' => null]);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        $notice = ChatMessage::where('sender_type', ChatMessage::SENDER_AI)->first();
        $this->assertNotNull($notice);
        $this->assertTrue($notice->ai_generated);
        $this->assertStringContainsString('pengaduan resmi', $notice->body);
    }

    public function test_does_not_repeat_the_acknowledgment_while_still_waiting_on_staff(): void
    {
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $first = $this->citizenMessage($ticket, 'halo');
        $this->fakeGeminiAnswer(['needs_human' => true, 'message' => null]);
        $this->handleJob(new AnswerChatMessageWithAiJob($first));

        $second = $this->citizenMessage($ticket, 'ada yang bisa bantu?');
        $this->handleJob(new AnswerChatMessageWithAiJob($second));

        $this->assertSame(1, ChatMessage::where('sender_type', ChatMessage::SENDER_AI)->count());
        $this->assertTrue($second->fresh()->escalation_flag);
    }

    public function test_ai_call_failure_escalates_instead_of_leaving_the_ticket_stuck(): void
    {
        $this->configureAi();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('server error', 500)]);
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        // A technical failure (not a complaint) still gets the plain "please wait"
        // acknowledgment, not the report-form suggestion — no cta_action here.
        $notice = ChatMessage::where('sender_type', ChatMessage::SENDER_AI)->first();
        $this->assertNotNull($notice);
        $this->assertNull($notice->cta_action);
        $this->assertTrue($message->fresh()->escalation_flag);
    }

    /**
     * A transient failure (provider 5xx) gets one immediate inline retry before falling
     * back to escalation — no queue-level backoff/re-dispatch, since a citizen is waiting
     * and the next cron cycle could be up to a minute away. See
     * AiSettingsService::isTransientFailure() and AnswerChatMessageWithAiJob::callAi().
     */
    public function test_a_transient_failure_is_retried_once_inline_and_can_still_succeed(): void
    {
        Queue::fake();
        $this->configureAi();
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push('server error', 500)
            ->push([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['needs_human' => false, 'message' => 'Kantor buka Senin-Jumat.'])]]]],
                ],
            ]);
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => $job->body === 'Kantor buka Senin-Jumat.');
        $this->assertFalse($message->fresh()->escalation_flag);
    }

    /**
     * A non-transient failure (bad API key, 401) must escalate straight away — retrying
     * would just fail identically, wasting the one shot a citizen is waiting on.
     */
    public function test_a_non_transient_failure_escalates_without_retrying(): void
    {
        $this->configureAi();
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push('unauthorized', 401)
            ->push('should never be reached', 500);
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        $this->assertTrue($message->fresh()->escalation_flag);
        Http::assertSentCount(1);
    }

    public function test_does_nothing_when_ai_is_not_configured(): void
    {
        Queue::fake();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        $this->assertSame(1, ChatMessage::count()); // only the citizen message exists
        $this->assertFalse($message->fresh()->escalation_flag);
        Queue::assertNothingPushed();
    }

    public function test_does_nothing_when_ai_was_disabled_on_the_ticket_before_the_job_ran(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket);
        $ticket->update(['ai_enabled' => false]);
        $this->fakeGeminiAnswer(['needs_human' => false, 'message' => 'Ini tidak akan pernah dikirim.']);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        $this->assertSame(0, ChatMessage::where('sender_type', ChatMessage::SENDER_AI)->count());
        $this->assertFalse($message->fresh()->escalation_flag);
        Queue::assertNothingPushed();
    }

    public function test_exceeding_the_per_ticket_hourly_ai_limit_escalates_instead_of_calling_ai(): void
    {
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket);

        RateLimiter::clear('chat-ai.'.$ticket->id);
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit('chat-ai.'.$ticket->id, 3600);
        }

        Http::fake(function () {
            $this->fail('AI should not have been called once the per-ticket limit is exhausted.');
        });

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        $this->assertTrue($message->fresh()->escalation_flag);
    }

    /**
     * Regression for a production bug: a small/cheap model can get anchored on its own
     * recent replies and echo a near-identical answer regardless of what's actually being
     * asked. This must be caught in code, not just discouraged by prompt wording.
     */
    public function test_a_near_duplicate_of_the_previous_ai_reply_is_escalated_instead_of_posted(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $earlier = $this->citizenMessage($ticket, 'kasih pantun perpisahan');
        ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_AI,
            'body' => 'Jalan-jalan ke kota Blitar, jangan lupa beli sukun. Cukup sekian obrolan kita sebentar, semoga harinya makin santun!',
            'ai_generated' => true,
            'created_at' => now(),
        ]);

        $newQuestion = $this->citizenMessage($ticket, 'syarat buat ktp apa ya?');
        $this->fakeGeminiAnswer([
            'needs_human' => false,
            'message' => 'Jalan-jalan ke kota Blitar, jangan lupa beli sukun. Cukup sekian obrolan sebentar, semoga harinya makin santun! Hehe.',
        ]);

        $this->handleJob(new AnswerChatMessageWithAiJob($newQuestion));

        // The hallucinated repeat itself must never reach the citizen — but escalate()
        // still queues its own plain acknowledgment (not the repeated text, no cta:
        // a stale-repeat catch isn't necessarily about a complaint).
        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => $job->body !== 'Jalan-jalan ke kota Blitar, jangan lupa beli sukun. Cukup sekian obrolan sebentar, semoga harinya makin santun! Hehe.'
            && $job->ctaAction === null);
        $this->assertTrue($newQuestion->fresh()->escalation_flag);
    }

    public function test_a_genuinely_different_reply_is_still_posted_even_when_an_earlier_ai_reply_exists(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_AI,
            'body' => 'Jam layanan kami Senin-Jumat, 08.00-16.00 WIB.',
            'ai_generated' => true,
            'created_at' => now(),
        ]);

        $newQuestion = $this->citizenMessage($ticket, 'syarat buat ktp apa ya?');
        $this->fakeGeminiAnswer([
            'needs_human' => false,
            'message' => 'Syarat perekaman KTP-el: usia 17 tahun/sudah menikah, bawa KK asli, dan surat pengantar RT/RW.',
        ]);

        $this->handleJob(new AnswerChatMessageWithAiJob($newQuestion));

        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => $job->body === 'Syarat perekaman KTP-el: usia 17 tahun/sudah menikah, bawa KK asli, dan surat pengantar RT/RW.');
        $this->assertFalse($newQuestion->fresh()->escalation_flag);
    }

    /**
     * Regression: ChatTicket::messages() bakes in ->orderBy('created_at') for chronological
     * display — repeatsRecentReply() used to chain ->latest('id') on top of that without
     * clearing it first, which only ADDS a secondary sort key rather than replacing the
     * inherited one. That silently compared every candidate against the ticket's very
     * FIRST-ever AI message instead of its most recent one, so the guard almost never
     * tripped in a real (long) conversation. Needs at least two prior AI turns to catch —
     * with only one, "first" and "latest" are the same message and the bug is invisible.
     * Strings below are the exact bodies from the real conversation that surfaced this.
     */
    public function test_repeat_detection_compares_against_the_true_latest_ai_message_not_the_first(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->citizenMessage($ticket, 'Hallo kak');
        ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_AI,
            'body' => 'Halo juga! Saya Tata dari Unit Layanan Pengaduan Disdukcapil. Ada yang bisa Tata bantu hari ini terkait layanan administrasi kependudukan atau pengaduan?',
            'ai_generated' => true,
            'created_at' => now(),
        ]);
        $this->citizenMessage($ticket, 'kak saya mau buat akta kelahiran saratnya apa ya??');
        ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_AI,
            'body' => 'Halo! Ada yang bisa Tata bantu lagi terkait layanan Disdukcapil atau pengecekan sesuatu?',
            'ai_generated' => true,
            'created_at' => now(),
        ]);

        $newQuestion = $this->citizenMessage($ticket, 'coba baca kembali pertanyaan sayaa');
        $this->fakeGeminiAnswer([
            'needs_human' => false,
            'message' => 'Oh iya, Halo! Ada yang bisa Tata bantu lagi terkait layanan Adminduk atau pengaduan kita?',
        ]);

        $this->handleJob(new AnswerChatMessageWithAiJob($newQuestion));

        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => $job->body !== 'Oh iya, Halo! Ada yang bisa Tata bantu lagi terkait layanan Adminduk atau pengaduan kita?'
            && $job->ctaAction === null);
        $this->assertTrue($newQuestion->fresh()->escalation_flag);
    }

    /**
     * Regression for a production bug found under concurrent testing: two citizen
     * messages sent a couple seconds apart each dispatch their own job, and both used to
     * independently answer the (by-then) same latest question — producing two near-
     * duplicate AI replies, since the anti-repeat guard only sees ALREADY-POSTED replies
     * and each answer is posted by a separately-delayed job. The fix must skip a job
     * outright once its triggering message is no longer the latest citizen message.
     */
    public function test_job_for_a_superseded_message_is_skipped_without_calling_ai(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $older = $this->citizenMessage($ticket, 'halo kak');
        $this->citizenMessage($ticket, 'kalau KK hilang gimana ngurusnya?');

        Http::fake(function () {
            $this->fail('AI should not have been called for a message already superseded by a newer one.');
        });

        $this->handleJob(new AnswerChatMessageWithAiJob($older));

        Queue::assertNotPushed(PostChatAiReplyJob::class);
        $this->assertFalse($older->fresh()->escalation_flag);
    }

    public function test_job_for_the_latest_message_still_answers_normally(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->citizenMessage($ticket, 'halo kak');
        $latest = $this->citizenMessage($ticket, 'kalau KK hilang gimana ngurusnya?');
        $this->fakeGeminiAnswer(['needs_human' => false, 'message' => 'Siapkan Surat Keterangan Kehilangan dari Kepolisian.']);

        $this->handleJob(new AnswerChatMessageWithAiJob($latest));

        Queue::assertPushed(PostChatAiReplyJob::class);
    }

    /**
     * Closes the narrower race: the citizen sends a second message WHILE the AI call for
     * the first is still in flight (the call itself can take a few seconds). The
     * superseded-check right before dispatching the delayed post job must catch this too,
     * not just the early check at the top of handle().
     */
    public function test_a_newer_message_arriving_mid_ai_call_prevents_the_stale_answer_from_posting(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $first = $this->citizenMessage($ticket, 'halo kak');

        Http::fake(function () use ($ticket) {
            // Simulate the citizen's second message landing in the DB while this
            // (fake) AI call is "in flight" — before the response is returned.
            $this->citizenMessage($ticket, 'kalau KK hilang gimana ngurusnya?');

            return Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['needs_human' => false, 'message' => 'Halo! Ada yang bisa dibantu?'])]]]],
                ],
            ]);
        });

        $this->handleJob(new AnswerChatMessageWithAiJob($first));

        Queue::assertNotPushed(PostChatAiReplyJob::class);
    }

    /**
     * Regression for a real production symptom: the citizen sends a follow-up while an
     * earlier message's AI job is still in flight (slow provider call). The AI eventually
     * declines (needs_human), but by then an officer has already replied — which flips
     * ai_enabled off and clears escalation flags. The stale "please wait" notice must not
     * be posted at that point; it would land after the officer's real reply, reading like
     * a disconnected bot glitch.
     */
    public function test_a_stale_decline_after_an_officer_already_replied_does_not_post_a_notice(): void
    {
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket, 'berapa lama kak??');
        $officer = User::factory()->create();

        Http::fake(function () use ($ticket, $officer) {
            // Simulate the officer stepping in and replying while this (fake) AI
            // call is "in flight" — before the response is returned.
            app(ChatAdminService::class)->sendMessage($ticket, $officer, 'Mohon maaf, boleh info namanya?');

            return Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['needs_human' => true, 'message' => null])]]]],
                ],
            ]);
        });

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        $this->assertSame(0, ChatMessage::where('sender_type', ChatMessage::SENDER_AI)->count());
        $this->assertFalse($ticket->fresh()->ai_enabled);
    }

    /**
     * Same stale-notice symptom, but via the "citizen sent another message" path
     * instead of an officer reply — the AI decline for the older message must not
     * surface a notice once a newer citizen message already exists.
     */
    public function test_a_stale_decline_after_a_newer_citizen_message_does_not_post_a_notice(): void
    {
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $first = $this->citizenMessage($ticket, 'tapi kemarin saya dimintai uang oleh petugas');

        Http::fake(function () use ($ticket) {
            $this->citizenMessage($ticket, 'berapa lama kak??');

            return Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['needs_human' => true, 'message' => null])]]]],
                ],
            ]);
        });

        $this->handleJob(new AnswerChatMessageWithAiJob($first));

        $this->assertSame(0, ChatMessage::where('sender_type', ChatMessage::SENDER_AI)->count());
    }

    /**
     * The new offer-first flow: instead of silently cutting off a citizen with an
     * officer-conduct complaint or an uncertain answer, the AI offers escalation as a
     * conversational choice — the citizen can still be answered/continue chatting.
     */
    public function test_ai_offering_escalation_posts_a_flagged_offer_message_without_a_system_notice(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket, 'Kemarin saya dimintai uang oleh petugas.');
        $this->fakeGeminiAnswer([
            'needs_human' => false,
            'message' => 'Untuk hal ini kayaknya lebih pas ditangani petugas berwenang ya. Mau Tata arahkan buat pengaduan resmi, atau masih mau lanjut ngobrol dulu?',
            'offer_escalation' => true,
            'cta' => null,
        ]);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        // Exactly one reply queued (the offer itself) — no separate hold-on
        // acknowledgment alongside it, since offering isn't the deadlock/escalate() path.
        Queue::assertPushed(PostChatAiReplyJob::class, 1);
        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => $job->offerEscalation === true && $job->ctaAction === null);
        $this->assertFalse($message->fresh()->escalation_flag);
    }

    /**
     * Deterministic backstop against the model failing to recognise its own deadlock:
     * if it already offered escalation recently (within the small recent window, not
     * the ticket's entire lifetime) and tries to offer AGAIN, force a real silent
     * handoff instead of repeating the same question at the citizen.
     */
    public function test_offering_escalation_again_shortly_after_a_recent_offer_escalates_instead(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $this->citizenMessage($ticket, 'Kemarin saya dimintai uang oleh petugas.');
        ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_AI,
            'body' => 'Mau Tata arahkan buat pengaduan resmi, atau masih mau lanjut ngobrol dulu?',
            'ai_generated' => true,
            'offer_escalation' => true,
            'created_at' => now(),
        ]);
        $decline = $this->citizenMessage($ticket, 'gak usah deh, lanjut aja ngobrol di sini');
        $this->fakeGeminiAnswer([
            'needs_human' => false,
            'message' => 'Baik, kalau begitu apakah ada hal lain yang bisa Tata bantu terkait itu?',
            'offer_escalation' => true,
            'cta' => null,
        ]);

        $this->handleJob(new AnswerChatMessageWithAiJob($decline));

        // The model's second offer attempt must never reach the citizen — forced into
        // the deadlock acknowledgment instead, flagged for the report form since this IS
        // the "already offered, citizen didn't take it, still stuck" case.
        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => $job->body !== 'Baik, kalau begitu apakah ada hal lain yang bisa Tata bantu terkait itu?'
            && $job->ctaAction === 'report_form');
        $this->assertTrue($decline->fresh()->escalation_flag);
    }

    /**
     * Citizen agreeing to a previously-offered escalation gets a fixed CTA button
     * (rendered by code, never model-authored) directing them to file a formal
     * complaint — and is flagged for the officer inbox as an audit trail, but without
     * the "please wait" system notice since the AI's own message already gives the
     * citizen a clear next step.
     */
    public function test_citizen_accepting_the_offer_posts_a_message_with_the_report_form_cta(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_AI,
            'body' => 'Mau Tata arahkan buat pengaduan resmi, atau masih mau lanjut ngobrol dulu?',
            'ai_generated' => true,
            'offer_escalation' => true,
            'created_at' => now(),
        ]);
        $accept = $this->citizenMessage($ticket, 'ya boleh, tolong arahkan');
        $this->fakeGeminiAnswer([
            'needs_human' => false,
            'message' => 'Baik, silakan klik tombol di bawah ini ya.',
            'offer_escalation' => false,
            'cta' => 'report_form',
        ]);

        $this->handleJob(new AnswerChatMessageWithAiJob($accept));

        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => $job->ctaAction === 'report_form' && $job->offerEscalation === false);
        $this->assertTrue($accept->fresh()->escalation_flag);
    }

    /**
     * A specific report-status question is answered directly with a link to the
     * existing "Cek Status" page — no offer/negotiation needed, since the redirect
     * itself is the deterministic, correct answer.
     */
    public function test_a_specific_report_status_question_gets_the_check_status_cta(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket, 'Laporan saya nomor WBS-2026-0001 statusnya gimana ya?');
        $this->fakeGeminiAnswer([
            'needs_human' => false,
            'message' => 'Untuk cek status laporan, silakan pakai halaman Cek Status ya.',
            'offer_escalation' => false,
            'cta' => 'check_status',
        ]);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => $job->ctaAction === 'check_status');
    }

    /**
     * A stateless AI call has no innate sense of "now" — a question about current
     * service hours ("masih buka gak sekarang?") needs the real wall-clock time given
     * explicitly so the model can compare it against the schedule, instead of just
     * reciting the schedule blindly regardless of when it's actually being asked.
     */
    public function test_the_prompt_sent_to_the_provider_includes_the_current_real_time(): void
    {
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket, 'Sekarang masih buka gak ya kantornya?');
        $expectedNow = now()->translatedFormat('l, d F Y, H:i').' WIB';

        Http::fake(function ($request) use ($expectedNow) {
            $this->assertStringContainsString($expectedNow, $request->body());

            return Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['needs_human' => false, 'message' => 'Iya, saat ini masih buka kok.'])]]]],
                ],
            ]);
        });

        $this->handleJob(new AnswerChatMessageWithAiJob($message));
    }

    public function test_an_officer_reply_clears_pending_escalation_flags(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket);
        $message->update(['escalation_flag' => true]);

        $officer = User::factory()->create();
        app(\App\Services\ChatAdminService::class)->sendMessage($ticket, $officer, 'Baik, akan saya bantu.');

        $this->assertFalse($message->fresh()->escalation_flag);
    }

    /**
     * Regression guard for the moderation feature: the AI must never be called for a
     * message that's already been flagged by ContentModerationService — the reply is a
     * fixed narrative, not left to the model (same lesson as repeatsRecentReply()).
     */
    public function test_a_moderation_flagged_message_bypasses_the_ai_call_entirely(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket, 'Dasar anjing goblok!');
        $message->update(['moderation_flagged' => true, 'escalation_flag' => true]);

        Http::fake(function () {
            $this->fail('AI should never be called for a moderation-flagged message.');
        });

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        Queue::assertPushed(PostChatAiReplyJob::class, fn ($job) => str_contains(
            $job->body,
            'akan saya lanjutkan ke pejabat yang berwenang, untuk penanganan lebih baik',
        ));
    }

    public function test_citizen_confirming_done_closes_the_ticket(): void
    {
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket, 'oke sudah jelas, terima kasih ya, tidak ada lagi');
        $this->fakeGeminiAnswer(['needs_human' => false, 'message' => 'Sama-sama!', 'citizen_confirmed_done' => true]);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        $this->assertSame(ChatTicket::STATUS_SELESAI, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    public function test_citizen_not_confirming_done_leaves_the_ticket_open(): void
    {
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $message = $this->citizenMessage($ticket, 'jam buka kantor jam berapa?');
        $this->fakeGeminiAnswer(['needs_human' => false, 'message' => 'Senin-Jumat 08.00-16.00.', 'citizen_confirmed_done' => false]);

        $this->handleJob(new AnswerChatMessageWithAiJob($message));

        $this->assertNotSame(ChatTicket::STATUS_SELESAI, $ticket->fresh()->status);
    }

    /**
     * Regression: buildContext() had the same ->orderBy('created_at') vs ->latest()
     * ordering bug as repeatsRecentReply() (see that regression test above) — once a
     * ticket passed 20 messages, "latest 20" silently became "oldest 20" instead, so the
     * AI's own context window excluded the triggering message and everything recent. The
     * AI would then be answering with zero visibility into what was actually just asked.
     */
    public function test_ai_context_includes_recent_messages_not_the_oldest_ones_once_a_ticket_grows_past_the_history_limit(): void
    {
        Queue::fake();
        $this->configureAi();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        // 22 older messages (11 citizen/AI pairs) with explicit, strictly increasing
        // timestamps — enough to push the true latest messages outside what an "oldest
        // 20" query would still capture, and deterministic regardless of how the DB
        // breaks ties on identical created_at values.
        $t = now()->subMinutes(30);
        for ($i = 1; $i <= 11; $i++) {
            ChatMessage::create([
                'chat_ticket_id' => $ticket->id,
                'sender_type' => ChatMessage::SENDER_CITIZEN,
                'body' => "pertanyaan lama nomor {$i}",
                'created_at' => $t->addSeconds(1),
            ]);
            ChatMessage::create([
                'chat_ticket_id' => $ticket->id,
                'sender_type' => ChatMessage::SENDER_AI,
                'body' => "jawaban lama nomor {$i}",
                'ai_generated' => true,
                'created_at' => $t->addSeconds(1),
            ]);
        }

        $newQuestion = ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_CITIZEN,
            'body' => 'PERTANYAAN_UNIK_TERBARU: syarat akta kelahiran apa saja?',
            'created_at' => $t->addSeconds(1),
        ]);
        $this->fakeGeminiAnswer(['needs_human' => false, 'message' => 'Syarat akta kelahiran: surat keterangan lahir, KK, dan KTP orang tua.']);

        $this->handleJob(new AnswerChatMessageWithAiJob($newQuestion));

        Http::assertSent(fn ($request) => str_contains($request->body(), 'PERTANYAAN_UNIK_TERBARU'));
    }
}
