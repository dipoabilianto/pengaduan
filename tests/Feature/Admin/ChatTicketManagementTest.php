<?php

namespace Tests\Feature\Admin;

use App\Events\Chat\ChatTicketClosed;
use App\Models\ChatMessage;
use App\Models\ChatTicket;
use App\Models\User;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatTicketManagementTest extends TestCase
{
    use RefreshDatabase;

    private function officerWithChatAccess(): User
    {
        $this->seed(RolesTableSeeder::class);

        $role = Role::firstOrCreate(['name' => 'Petugas Chat', 'guard_name' => 'web']);
        $role->syncPermissions(['chat.lihat', 'chat.balas', 'chat.tutup', 'chat.lihat-nomor-telepon']);

        $user = User::factory()->create();
        $user->assignRole('Petugas Chat');

        return $user;
    }

    private function officerWithoutChatAccess(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Pengawas');

        return $user;
    }

    public function test_officer_without_chat_permission_cannot_view_inbox(): void
    {
        $this->actingAs($this->officerWithoutChatAccess())
            ->get(route('admin.chat.index'))
            ->assertForbidden();
    }

    public function test_officer_with_chat_permission_can_view_inbox(): void
    {
        $this->actingAs($this->officerWithChatAccess())
            ->get(route('admin.chat.index'))
            ->assertOk();
    }

    public function test_officer_can_send_a_reply_which_auto_claims_and_disables_ai(): void
    {
        $officer = $this->officerWithChatAccess();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->actingAs($officer)->post(route('admin.chat.send', $ticket), [
            'message' => 'Silakan datang ke kantor kami jam 08.00-16.00.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame($officer->id, $ticket->assigned_to);
        $this->assertSame(ChatTicket::STATUS_DITANGANI, $ticket->status);
        $this->assertFalse($ticket->ai_enabled);
        $this->assertSame(1, ChatMessage::where('sender_type', ChatMessage::SENDER_OFFICER)->count());
    }

    public function test_officer_reply_containing_profanity_is_rejected_and_never_sent(): void
    {
        $officer = $this->officerWithChatAccess();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->actingAs($officer)->post(route('admin.chat.send', $ticket), [
            'message' => 'Dasar anjing, laporan kamu tidak jelas.',
        ])->assertRedirect()->assertSessionHasErrors('message');

        $this->assertSame(0, ChatMessage::where('sender_type', ChatMessage::SENDER_OFFICER)->count());
    }

    public function test_officer_without_reply_permission_cannot_send_a_message(): void
    {
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->actingAs($this->officerWithoutChatAccess())
            ->post(route('admin.chat.send', $ticket), ['message' => 'Mencoba tanpa izin.'])
            ->assertForbidden();

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_officer_can_mark_a_ticket_selesai(): void
    {
        $officer = $this->officerWithChatAccess();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->actingAs($officer)->patch(route('admin.chat.status', $ticket), [
            'status' => ChatTicket::STATUS_SELESAI,
        ])->assertRedirect();

        $this->assertSame(ChatTicket::STATUS_SELESAI, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    /**
     * Lets the citizen's own widget offer the satisfaction rating card live — this
     * covers the officer-driven closer; AnswerChatMessageWithAiJob's citizen-confirmed
     * auto-close and ProcessInactiveChatTicketsCommand's 6h auto-close both funnel
     * through the same ChatAdminService::updateStatus(), so they're covered for free.
     */
    public function test_marking_a_ticket_selesai_broadcasts_a_ticket_closed_event(): void
    {
        Event::fake([ChatTicketClosed::class]);
        $officer = $this->officerWithChatAccess();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->actingAs($officer)->patch(route('admin.chat.status', $ticket), [
            'status' => ChatTicket::STATUS_SELESAI,
        ]);

        Event::assertDispatched(ChatTicketClosed::class, fn ($event) => $event->chatTicketId === $ticket->id);
    }

    public function test_reopening_a_ticket_does_not_broadcast_a_ticket_closed_event(): void
    {
        Event::fake([ChatTicketClosed::class]);
        $officer = $this->officerWithChatAccess();
        $ticket = ChatTicket::findOrStartFor('081234567890');
        $ticket->update(['status' => ChatTicket::STATUS_SELESAI, 'closed_at' => now()]);

        $this->actingAs($officer)->patch(route('admin.chat.status', $ticket), [
            'status' => ChatTicket::STATUS_MENUNGGU,
        ]);

        Event::assertNotDispatched(ChatTicketClosed::class);
    }

    public function test_revealing_phone_writes_an_audit_log_entry(): void
    {
        $officer = $this->officerWithChatAccess();
        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->actingAs($officer)->post(route('admin.chat.reveal-phone', $ticket))->assertRedirect();

        $this->assertSame(1, \App\Models\AuditLog::where('subject_type', ChatTicket::class)->where('subject_id', $ticket->id)->count());
    }

    public function test_a_role_without_the_phone_permission_cannot_reveal_it(): void
    {
        $this->seed(RolesTableSeeder::class);
        $role = Role::firstOrCreate(['name' => 'Petugas Terbatas', 'guard_name' => 'web']);
        $role->syncPermissions(['chat.lihat', 'chat.balas']);
        $user = User::factory()->create();
        $user->assignRole('Petugas Terbatas');

        $ticket = ChatTicket::findOrStartFor('081234567890');

        $this->actingAs($user)->post(route('admin.chat.reveal-phone', $ticket))->assertForbidden();
    }
}
