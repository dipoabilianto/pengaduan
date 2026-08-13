<?php

namespace Tests\Unit\Models;

use App\Models\ChatTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_phone_number_reuses_the_same_ticket(): void
    {
        $first = ChatTicket::findOrStartFor('081234567890');
        $second = ChatTicket::findOrStartFor('081234567890');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ChatTicket::count());
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
