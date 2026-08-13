<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\ChannelInbox;
use App\Models\User;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelInboxControllerTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Administrator');

        return $user;
    }

    private function pengawas(): User
    {
        $this->seed(RolesTableSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Pengawas'); // does not have laporan.kelola-inbox

        return $user;
    }

    private function inboxItem(): ChannelInbox
    {
        return ChannelInbox::create([
            'source' => 'whatsapp',
            'external_ref' => '081234567890',
            'raw_message' => 'Ada jalan rusak di depan kantor kelurahan.',
            'status' => 'baru',
        ]);
    }

    public function test_user_without_permission_cannot_view_index(): void
    {
        $this->actingAs($this->pengawas())
            ->get(route('admin.inbox.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_index(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('admin.inbox.index'))
            ->assertOk()
            ->assertSee('Inbox Terpadu');
    }

    public function test_user_without_permission_cannot_convert(): void
    {
        $item = $this->inboxItem();

        $this->actingAs($this->pengawas())
            ->post(route('admin.inbox.convert', $item))
            ->assertForbidden();
    }

    public function test_convert_creates_a_report_via_report_service(): void
    {
        $item = $this->inboxItem();

        $this->actingAs($this->administrator())
            ->post(route('admin.inbox.convert', $item))
            ->assertRedirect();

        $item->refresh();

        $this->assertSame('dikonversi', $item->status);
        $this->assertNotNull($item->linked_report_id);
        $this->assertSame('whatsapp', $item->linkedReport->channel);
        $this->assertSame($item->raw_message, $item->linkedReport->what);
    }

    public function test_ignore_marks_item_as_diabaikan(): void
    {
        $item = $this->inboxItem();
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post(route('admin.inbox.ignore', $item))
            ->assertRedirect();

        $item->refresh();

        $this->assertSame('diabaikan', $item->status);
        $this->assertSame($admin->id, $item->handled_by);
        $this->assertNotNull($item->handled_at);
    }
}
