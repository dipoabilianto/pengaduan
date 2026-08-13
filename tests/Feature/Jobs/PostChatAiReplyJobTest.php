<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Chat\PostChatAiReplyJob;
use App\Models\ChatMessage;
use App\Models\ChatTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostChatAiReplyJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_ai_message_and_updates_the_ticket_preview(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');

        (new PostChatAiReplyJob($ticket->id, 'Kantor buka Senin-Jumat, 08.00-16.00 WIB.'))->handle();

        $message = ChatMessage::first();
        $this->assertNotNull($message);
        $this->assertSame(ChatMessage::SENDER_AI, $message->sender_type);
        $this->assertTrue($message->ai_generated);
        $this->assertSame('Kantor buka Senin-Jumat, 08.00-16.00 WIB.', $message->body);
        $this->assertSame('Kantor buka Senin-Jumat, 08.00-16.00 WIB.', $ticket->fresh()->last_message_preview);
    }

    public function test_it_persists_offer_escalation_and_cta_action(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');

        (new PostChatAiReplyJob($ticket->id, 'Mau diarahkan buat pengaduan resmi?', true, null))->handle();

        $message = ChatMessage::first();
        $this->assertTrue($message->offer_escalation);
        $this->assertNull($message->cta_action);
    }

    public function test_it_persists_the_cta_action(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');

        (new PostChatAiReplyJob($ticket->id, 'Silakan klik tombol di bawah.', false, 'report_form'))->handle();

        $message = ChatMessage::first();
        $this->assertFalse($message->offer_escalation);
        $this->assertSame('report_form', $message->cta_action);
    }

    public function test_it_does_nothing_if_ai_was_disabled_before_it_ran(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $ticket->update(['ai_enabled' => false]);

        (new PostChatAiReplyJob($ticket->id, 'Ini seharusnya tidak pernah terkirim.'))->handle();

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_it_does_nothing_if_the_ticket_no_longer_exists(): void
    {
        (new PostChatAiReplyJob(999999, 'Tiket ini tidak ada.'))->handle();

        $this->assertSame(0, ChatMessage::count());
    }
}
