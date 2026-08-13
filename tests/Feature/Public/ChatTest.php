<?php

namespace Tests\Feature\Public;

use App\Models\ChatMessage;
use App\Models\ChatRating;
use App\Models\ChatTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * /chat/mulai requires a solved CAPTCHA (see StartChatRequest) — fetches a fresh
     * code from the session (mirroring what the widget's refreshCaptcha() does) and
     * submits it alongside the phone number. A solved code is forgotten by the
     * controller on success, so each call needs its own fresh one.
     */
    private function startChat(string $phone, array $extra = []): TestResponse
    {
        $code = $this->getJson(route('captcha'))->json('code');

        return $this->postJson(route('chat.start'), array_merge(['phone' => $phone, 'captcha' => $code], $extra));
    }

    public function test_starting_a_chat_creates_a_ticket_and_returns_a_session_token(): void
    {
        $response = $this->startChat('081234567890');

        $response->assertOk();
        $ticket = ChatTicket::first();
        $this->assertNotNull($ticket);
        $response->assertJson(['ticket_id' => $ticket->id, 'status' => ChatTicket::STATUS_MENUNGGU]);
        $this->assertSame($ticket->channel_token, session('chat_token_'.$ticket->id));
    }

    public function test_starting_a_chat_without_a_captcha_is_rejected(): void
    {
        $response = $this->postJson(route('chat.start'), ['phone' => '081234567890']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('captcha');
        $this->assertSame(0, ChatTicket::count());
    }

    public function test_starting_a_chat_with_a_wrong_captcha_is_rejected(): void
    {
        $this->getJson(route('captcha'));

        $response = $this->postJson(route('chat.start'), ['phone' => '081234567890', 'captcha' => 'SALAH']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('captcha');
        $this->assertSame(0, ChatTicket::count());
    }

    public function test_starting_a_chat_twice_with_the_same_phone_resumes_the_same_ticket(): void
    {
        $this->startChat('081234567890');
        $this->startChat('081234567890');

        $this->assertSame(1, ChatTicket::count());
    }

    /**
     * A closed ticket is never silently reused for a new conversation — see
     * ChatTicket::findOrStartFor().
     */
    public function test_starting_a_chat_again_after_the_previous_one_closed_creates_a_new_ticket(): void
    {
        $this->startChat('081234567890');
        $first = ChatTicket::first();
        $first->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);

        $this->startChat('081234567890');

        $this->assertSame(2, ChatTicket::count());
    }

    public function test_a_citizen_can_send_a_message_to_their_own_ticket(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();

        $response = $this->postJson(route('chat.send', $ticket), ['message' => 'Halo, mau tanya jam buka.']);

        $response->assertOk();
        $this->assertSame(1, ChatMessage::where('chat_ticket_id', $ticket->id)->count());
        $message = ChatMessage::first();
        $this->assertSame(ChatMessage::SENDER_CITIZEN, $message->sender_type);
        $this->assertSame('Halo, mau tanya jam buka.', $message->body);
    }

    public function test_sending_a_message_without_a_valid_session_token_is_forbidden(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');

        // No prior /chat/mulai call in this test's session — no token issued.
        $response = $this->postJson(route('chat.send', $ticket), ['message' => 'Mencoba tanpa token.']);

        $response->assertForbidden();
        $this->assertSame(0, ChatMessage::count());
    }

    /**
     * A guest holding ticket A's session token must not be able to act on ticket B —
     * regression test for guest-to-guest ticket isolation.
     */
    public function test_a_guest_cannot_send_a_message_to_someone_elses_ticket(): void
    {
        $this->startChat('081234567890');
        $ownTicket = ChatTicket::first();

        $otherTicket = ChatTicket::findOrStartFor('089876543210');

        $response = $this->postJson(route('chat.send', $otherTicket), ['message' => 'Mencoba akses ticket orang lain.']);

        $response->assertForbidden();
        $this->assertSame(0, ChatMessage::count());
        // Sanity: the session token this guest DOES hold still only matches their own ticket.
        $this->assertNotSame($otherTicket->channel_token, session('chat_token_'.$ownTicket->id));
    }

    public function test_sending_a_message_reopens_a_closed_ticket(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);

        $this->postJson(route('chat.send', $ticket), ['message' => 'Masih ada pertanyaan lagi.']);

        $this->assertSame(ChatTicket::STATUS_MENUNGGU, $ticket->fresh()->status);
    }

    public function test_sending_a_message_records_citizen_activity_timestamp(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();

        $this->postJson(route('chat.send', $ticket), ['message' => 'Halo, mau tanya jam buka.']);

        $this->assertNotNull($ticket->fresh()->last_citizen_message_at);
    }

    public function test_a_message_with_profanity_is_flagged_but_still_sent(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();

        $this->postJson(route('chat.send', $ticket), ['message' => 'Dasar anjing lambat banget!']);

        $message = ChatMessage::first();
        $this->assertTrue($message->moderation_flagged);
        $this->assertTrue($message->escalation_flag);
        $this->assertSame(1, ChatMessage::count()); // still just the one citizen message — not rejected
    }

    public function test_a_normal_message_is_not_flagged(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();

        $this->postJson(route('chat.send', $ticket), ['message' => 'Halo, mau tanya jam buka.']);

        $this->assertFalse(ChatMessage::first()->moderation_flagged);
    }

    public function test_history_is_returned_in_full_for_a_ticket_closed_less_than_six_hours_ago(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        ChatMessage::create(['chat_ticket_id' => $ticket->id, 'sender_type' => ChatMessage::SENDER_CITIZEN, 'body' => 'halo', 'created_at' => now()]);
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()->subHours(2)]);

        $response = $this->getJson(route('chat.session'));

        $response->assertJson(['active' => true, 'history_hidden' => false]);
        $this->assertCount(1, $response->json('messages'));
    }

    public function test_history_is_hidden_between_six_and_twelve_hours_after_closing(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        ChatMessage::create(['chat_ticket_id' => $ticket->id, 'sender_type' => ChatMessage::SENDER_CITIZEN, 'body' => 'halo', 'created_at' => now()]);
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()->subHours(8)]);

        $response = $this->getJson(route('chat.session'));

        $response->assertJson(['active' => true, 'history_hidden' => true]);
        $this->assertCount(0, $response->json('messages'));
    }

    public function test_reveal_history_flag_returns_full_history_within_the_hidden_window(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        ChatMessage::create(['chat_ticket_id' => $ticket->id, 'sender_type' => ChatMessage::SENDER_CITIZEN, 'body' => 'halo', 'created_at' => now()]);
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()->subHours(8)]);

        $response = $this->getJson(route('chat.session', ['reveal_history' => true]));

        $response->assertJson(['active' => true, 'history_hidden' => false]);
        $this->assertCount(1, $response->json('messages'));
    }

    public function test_history_is_gone_without_reveal_option_past_twelve_hours(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        ChatMessage::create(['chat_ticket_id' => $ticket->id, 'sender_type' => ChatMessage::SENDER_CITIZEN, 'body' => 'halo', 'created_at' => now()]);
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()->subHours(13)]);

        $response = $this->getJson(route('chat.session'));

        $response->assertJson(['active' => true, 'history_hidden' => false]);
        $this->assertCount(0, $response->json('messages'));
    }

    public function test_starting_a_chat_with_a_ticket_no_links_the_related_report_and_logs_a_system_message(): void
    {
        $report = \App\Models\Report::create([
            'ticket_no' => 'WBS-TEST-0001',
            'type' => 'pengaduan',
            'category' => 'Lainnya',
            'channel' => 'web',
            'status' => 'baru_masuk',
            'what' => 'Contoh laporan.',
        ]);

        $this->startChat('081234567890', ['related_ticket_no' => 'WBS-TEST-0001']);

        $ticket = ChatTicket::first();
        $this->assertSame($report->id, $ticket->related_report_id);
        $this->assertSame(ChatMessage::SENDER_SYSTEM, ChatMessage::first()->sender_type);
    }

    public function test_a_citizen_can_rate_a_closed_chat(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);

        $response = $this->postJson(route('chat.rate', $ticket), ['scale' => 4, 'comment' => 'Sangat membantu!']);

        $response->assertOk()->assertJson(['status' => 'ok']);
        $rating = ChatRating::first();
        $this->assertNotNull($rating);
        $this->assertSame(4, $rating->scale);
        $this->assertSame('Sangat membantu!', $rating->comment);
    }

    public function test_rating_without_a_valid_session_token_is_forbidden(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);

        $this->postJson(route('chat.rate', $ticket), ['scale' => 4])->assertForbidden();
        $this->assertSame(0, ChatRating::count());
    }

    public function test_rating_a_chat_that_is_not_yet_closed_is_rejected(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();

        $this->postJson(route('chat.rate', $ticket), ['scale' => 3])->assertStatus(422);
        $this->assertSame(0, ChatRating::count());
    }

    public function test_rating_a_chat_twice_does_not_create_a_duplicate_row(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);

        $this->postJson(route('chat.rate', $ticket), ['scale' => 4]);
        $response = $this->postJson(route('chat.rate', $ticket), ['scale' => 1]);

        $response->assertOk()->assertJson(['status' => 'already_rated']);
        $this->assertSame(1, ChatRating::count());
        $this->assertSame(4, ChatRating::first()->scale);
    }

    public function test_a_reopened_ticket_can_be_rated_again_for_its_new_closure(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()->subDay()]);
        ChatRating::create(['chat_ticket_id' => $ticket->id, 'scale' => 2, 'created_at' => now()->subDay()]);

        // A fresh closure after that old rating — closed_at reset forward in time.
        $ticket->update(['closed_at' => now()]);

        $this->postJson(route('chat.rate', $ticket), ['scale' => 4]);

        $this->assertSame(2, ChatRating::count());
    }

    public function test_awaiting_rating_is_true_for_a_closed_unrated_ticket(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);

        $response = $this->getJson(route('chat.session'));

        $response->assertJson(['active' => true, 'awaiting_rating' => true]);
    }

    public function test_awaiting_rating_is_false_once_already_rated(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);
        ChatRating::create(['chat_ticket_id' => $ticket->id, 'scale' => 3, 'created_at' => now()]);

        $response = $this->getJson(route('chat.session'));

        $response->assertJson(['active' => true, 'awaiting_rating' => false]);
    }

    public function test_awaiting_rating_is_false_once_history_is_fully_gone(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()->subHours(13)]);

        $response = $this->getJson(route('chat.session'));

        $response->assertJson(['active' => true, 'awaiting_rating' => false]);
    }

    public function test_session_endpoint_reports_inactive_with_no_prior_start_call(): void
    {
        $response = $this->getJson(route('chat.session'));

        $response->assertOk()->assertJson(['active' => false]);
    }

    public function test_session_endpoint_resumes_the_active_ticket_with_no_phone_in_the_request(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();

        $response = $this->getJson(route('chat.session'));

        $response->assertOk()->assertJson(['active' => true, 'ticket_id' => $ticket->id]);
    }

    public function test_end_session_then_session_check_reports_inactive(): void
    {
        $this->startChat('081234567890');

        $this->postJson(route('chat.end-session'))->assertOk()->assertJson(['status' => 'ok']);

        $this->getJson(route('chat.session'))->assertJson(['active' => false]);
    }

    public function test_end_session_prevents_reusing_the_old_session_token_against_the_ticket(): void
    {
        $this->startChat('081234567890');
        $ticket = ChatTicket::first();

        $this->postJson(route('chat.end-session'));

        // The token stored under the ticket-specific session key is gone too, not just
        // the "which ticket is active" pointer — sendMessage/rate must also be denied.
        $this->postJson(route('chat.send', $ticket), ['message' => 'Setelah logout.'])->assertForbidden();
    }
}
