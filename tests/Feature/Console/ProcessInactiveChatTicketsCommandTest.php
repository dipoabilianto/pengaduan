<?php

namespace Tests\Feature\Console;

use App\Models\ChatMessage;
use App\Models\ChatTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessInactiveChatTicketsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(array $attributes = []): ChatTicket
    {
        return ChatTicket::create(array_merge([
            'phone_hash' => 'hash-'.uniqid(),
            'phone_enc' => '081234567890',
            'channel_token' => 'token-'.uniqid(),
            'status' => ChatTicket::STATUS_MENUNGGU,
            'ai_enabled' => true,
            'last_citizen_message_at' => now(),
            'nudge_count' => 0,
        ], $attributes));
    }

    public function test_sends_first_nudge_after_one_hour_of_silence(): void
    {
        $ticket = $this->ticket(['last_citizen_message_at' => now()->subHours(1)->subMinute()]);

        $this->artisan('chat:process-inactive-tickets')->assertSuccessful();

        $this->assertSame(1, $ticket->fresh()->nudge_count);
        $this->assertSame(1, ChatMessage::where('chat_ticket_id', $ticket->id)->where('sender_type', ChatMessage::SENDER_AI)->count());
    }

    public function test_does_not_nudge_again_within_the_same_run_or_before_the_next_threshold(): void
    {
        $ticket = $this->ticket(['last_citizen_message_at' => now()->subHours(1)->subMinute()]);

        $this->artisan('chat:process-inactive-tickets');
        $this->artisan('chat:process-inactive-tickets');

        // Still only 1 nudge — second threshold (3h) not reached yet, and nudge_count
        // is now 1 so the "first nudge" query no longer matches this ticket.
        $this->assertSame(1, $ticket->fresh()->nudge_count);
    }

    public function test_sends_second_nudge_after_three_hours_of_silence(): void
    {
        $ticket = $this->ticket(['last_citizen_message_at' => now()->subHours(3)->subMinute(), 'nudge_count' => 1]);

        $this->artisan('chat:process-inactive-tickets');

        $this->assertSame(2, $ticket->fresh()->nudge_count);
    }

    public function test_does_not_send_a_third_nudge(): void
    {
        $ticket = $this->ticket(['last_citizen_message_at' => now()->subHours(5), 'nudge_count' => 2]);

        $this->artisan('chat:process-inactive-tickets');

        $this->assertSame(2, $ticket->fresh()->nudge_count);
        $this->assertSame(0, ChatMessage::where('chat_ticket_id', $ticket->id)->count());
    }

    public function test_auto_closes_a_ticket_silent_for_six_hours(): void
    {
        $ticket = $this->ticket(['last_citizen_message_at' => now()->subHours(6)->subMinute(), 'nudge_count' => 2]);

        $this->artisan('chat:process-inactive-tickets');

        $ticket->refresh();
        $this->assertSame(ChatTicket::STATUS_SELESAI, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_does_not_touch_a_ticket_that_is_still_recently_active(): void
    {
        $ticket = $this->ticket(['last_citizen_message_at' => now()->subMinutes(10)]);

        $this->artisan('chat:process-inactive-tickets');

        $ticket->refresh();
        $this->assertSame(0, $ticket->nudge_count);
        $this->assertSame(ChatTicket::STATUS_MENUNGGU, $ticket->status);
    }

    public function test_does_not_touch_a_ticket_already_marked_selesai(): void
    {
        $ticket = $this->ticket([
            'last_citizen_message_at' => now()->subHours(10),
            'status' => ChatTicket::STATUS_SELESAI,
            'closed_at' => now()->subHours(9),
        ]);

        $this->artisan('chat:process-inactive-tickets');

        $this->assertSame(0, ChatMessage::where('chat_ticket_id', $ticket->id)->count());
    }
}
