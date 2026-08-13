<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\ChannelInbox;
use App\Models\User;
use App\Services\InstagramSettingsService;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelInboxReplyTest extends TestCase
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

    private function configureInstagram(): void
    {
        app(InstagramSettingsService::class)->save([
            'business_account_id' => '17841400000000000',
            'access_token' => 'fake-token',
            'webhook_verify_token' => 'verify-token',
        ], User::factory()->create());
    }

    private function dmItem(): ChannelInbox
    {
        return ChannelInbox::create([
            'source' => 'instagram',
            'external_type' => 'message',
            'external_ref' => '1234567890',
            'raw_message' => 'Halo, mau tanya soal KTP',
            'status' => 'baru',
        ]);
    }

    private function commentItem(): ChannelInbox
    {
        return ChannelInbox::create([
            'source' => 'instagram',
            'external_type' => 'comment',
            'external_ref' => 'warga_tubaba',
            'external_id' => 'comment-1',
            'raw_message' => 'Pelayanannya lama sekali',
            'status' => 'baru',
        ]);
    }

    private function whatsappItem(): ChannelInbox
    {
        return ChannelInbox::create([
            'source' => 'whatsapp',
            'external_ref' => '081234567890',
            'raw_message' => 'Ada jalan rusak.',
            'status' => 'baru',
        ]);
    }

    public function test_user_without_permission_cannot_reply(): void
    {
        $item = $this->dmItem();

        $this->actingAs($this->pengawas())
            ->post(route('admin.inbox.reply', $item), ['message' => 'Terima kasih laporannya.'])
            ->assertForbidden();
    }

    public function test_replying_to_a_whatsapp_item_is_rejected(): void
    {
        $item = $this->whatsappItem();

        $this->actingAs($this->administrator())
            ->post(route('admin.inbox.reply', $item), ['message' => 'Terima kasih laporannya.'])
            ->assertStatus(422);
    }

    public function test_reply_requires_a_message(): void
    {
        $item = $this->dmItem();

        $this->actingAs($this->administrator())
            ->post(route('admin.inbox.reply', $item), [])
            ->assertSessionHasErrors('message');
    }

    public function test_reply_flashes_an_error_when_instagram_is_not_configured(): void
    {
        $item = $this->dmItem();

        $this->actingAs($this->administrator())
            ->post(route('admin.inbox.reply', $item), ['message' => 'Terima kasih laporannya.'])
            ->assertSessionHasErrors('message');

        $this->assertSame('baru', $item->fresh()->status);
    }

    public function test_replying_to_a_dm_sends_a_direct_message_and_marks_ditriase(): void
    {
        $this->configureInstagram();
        Http::fake(['graph.facebook.com/*' => Http::response(['message_id' => 'mid.reply'])]);
        $item = $this->dmItem();
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->post(route('admin.inbox.reply', $item), ['message' => 'Terima kasih, mohon lengkapi berkas di loket 2.'])
            ->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/17841400000000000/messages')
            && $request['recipient']['id'] === '1234567890'
            && $request['message']['text'] === 'Terima kasih, mohon lengkapi berkas di loket 2.');

        $item->refresh();
        $this->assertSame('ditriase', $item->status);
        $this->assertSame($admin->id, $item->handled_by);
        $this->assertNotNull($item->handled_at);
    }

    public function test_replying_to_a_comment_posts_a_public_reply_and_marks_ditriase(): void
    {
        $this->configureInstagram();
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'reply-1'])]);
        $item = $this->commentItem();

        $this->actingAs($this->administrator())
            ->post(route('admin.inbox.reply', $item), ['message' => 'Terima kasih atas masukannya.'])
            ->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/comment-1/replies')
            && $request['message'] === 'Terima kasih atas masukannya.');

        $this->assertSame('ditriase', $item->fresh()->status);
    }

    public function test_reply_flashes_an_error_when_the_graph_api_call_fails(): void
    {
        $this->configureInstagram();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'invalid token'], 401)]);
        $item = $this->dmItem();

        $this->actingAs($this->administrator())
            ->post(route('admin.inbox.reply', $item), ['message' => 'Terima kasih laporannya.'])
            ->assertSessionHasErrors('message');

        $this->assertSame('baru', $item->fresh()->status);
    }

    public function test_reply_button_only_shows_for_instagram_sourced_items(): void
    {
        $this->configureInstagram();
        $instagramItem = $this->dmItem();
        $whatsappItem = $this->whatsappItem();

        $response = $this->actingAs($this->administrator())->get(route('admin.inbox.index'));

        $response->assertOk();
        $content = $response->getContent();

        // Both rows render — just confirm the "Balas" button/form appears once (for the
        // Instagram row) via the reply route being present exactly once in the markup.
        $this->assertSame(
            1,
            substr_count($content, route('admin.inbox.reply', $instagramItem)),
        );
        $this->assertSame(
            0,
            substr_count($content, route('admin.inbox.reply', $whatsappItem)),
        );
    }
}
