<?php

namespace Tests\Unit\Models;

use App\Models\ChatMessage;
use App\Models\ChatTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_phone_number_reuses_the_same_ticket_while_it_is_still_active(): void
    {
        $first = ChatTicket::findOrStartFor('081234567890');
        $second = ChatTicket::findOrStartFor('081234567890');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ChatTicket::count());
    }

    /**
     * A closed ticket is never silently reused for a new conversation — a phone
     * number can have multiple tickets over time (one per "life" of a conversation),
     * unlike the old forever-bound-to-one-ticket behavior.
     */
    public function test_same_phone_number_gets_a_new_ticket_once_the_previous_one_is_closed(): void
    {
        $first = ChatTicket::findOrStartFor('081234567890');
        $first->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);

        $second = ChatTicket::findOrStartFor('081234567890');

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->channel_token, $second->channel_token);
        $this->assertSame(ChatTicket::STATUS_MENUNGGU, $second->status);
        $this->assertSame(2, ChatTicket::count());
    }

    public function test_messages_of_two_tickets_for_the_same_phone_number_do_not_mix(): void
    {
        $first = ChatTicket::findOrStartFor('081234567890');
        $first->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);
        ChatMessage::create([
            'chat_ticket_id' => $first->id,
            'sender_type' => ChatMessage::SENDER_CITIZEN,
            'body' => 'Pesan di ticket pertama',
            'created_at' => now(),
        ]);

        $second = ChatTicket::findOrStartFor('081234567890');
        ChatMessage::create([
            'chat_ticket_id' => $second->id,
            'sender_type' => ChatMessage::SENDER_CITIZEN,
            'body' => 'Pesan di ticket kedua',
            'created_at' => now(),
        ]);

        $this->assertCount(1, $first->messages);
        $this->assertCount(1, $second->messages);
        $this->assertSame('Pesan di ticket pertama', $first->messages->first()->body);
        $this->assertSame('Pesan di ticket kedua', $second->messages->first()->body);
    }

    /**
     * normalizePhone() only strips punctuation/whitespace (see ReportReporter),
     * it does NOT unify the "0" vs "62" country-code prefix — "081234..." and
     * "+62812..." are genuinely different digit strings and get different tickets.
     * Same digit sequence with different formatting DOES collide, which is what
     * this actually tests.
     */
    public function test_differently_formatted_same_digits_hash_to_the_same_ticket(): void
    {
        $first = ChatTicket::findOrStartFor('081234567890');
        $second = ChatTicket::findOrStartFor('0812-3456-7890');

        $this->assertSame($first->id, $second->id);
    }

    public function test_different_phone_numbers_get_different_tickets(): void
    {
        $first = ChatTicket::findOrStartFor('081234567890');
        $second = ChatTicket::findOrStartFor('089876543210');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, ChatTicket::count());
    }

    public function test_new_ticket_starts_menunggu_respon_with_ai_enabled(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->assertSame(ChatTicket::STATUS_MENUNGGU, $ticket->status);
        $this->assertTrue($ticket->ai_enabled);
        $this->assertNotEmpty($ticket->channel_token);
    }

    public function test_a_closed_ticket_reopens_on_new_activity(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);

        $ticket->reopen();

        $this->assertSame(ChatTicket::STATUS_MENUNGGU, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->closed_at);
    }

    public function test_reopen_is_a_no_op_when_not_closed(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $ticket->update(['status' => ChatTicket::STATUS_DITANGANI]);

        $ticket->reopen();

        $this->assertSame(ChatTicket::STATUS_DITANGANI, $ticket->fresh()->status);
    }

    public function test_phone_is_encrypted_at_rest_but_hash_is_queryable(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $raw = DB::table('chat_tickets')->where('id', $ticket->id)->first();
        $this->assertNotSame('081234567890', $raw->phone_enc);
        $this->assertSame('081234567890', $ticket->fresh()->phone_enc);
    }
}
