<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\ChannelInbox;
use App\Models\User;
use App\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function configureVerifyToken(string $token = 'my-verify-token'): void
    {
        app(WhatsAppSettingsService::class)->save([
            'phone_number_id' => '109876543210987',
            'access_token' => 'fake-token',
            'webhook_verify_token' => $token,
        ], User::factory()->create());
    }

    public function test_verify_succeeds_with_the_correct_token(): void
    {
        $this->configureVerifyToken('correct-token');

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=correct-token&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_verify_fails_with_the_wrong_token(): void
    {
        $this->configureVerifyToken('correct-token');

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong-token&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_verify_fails_when_not_configured(): void
    {
        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=anything&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_receive_creates_a_channel_inbox_row_from_a_valid_payload(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    ['from' => '6281234567890', 'text' => ['body' => 'Halo, ada jalan rusak']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/whatsapp', $payload)->assertOk();

        $this->assertDatabaseHas('channel_inbox', [
            'source' => 'whatsapp',
            'external_ref' => '6281234567890',
            'raw_message' => 'Halo, ada jalan rusak',
            'status' => 'baru',
        ]);
    }

    public function test_receive_handles_multiple_messages_in_one_payload(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    ['from' => '6281111111111', 'text' => ['body' => 'Pesan satu']],
                                    ['from' => '6282222222222', 'text' => ['body' => 'Pesan dua']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/whatsapp', $payload)->assertOk();

        $this->assertSame(2, ChannelInbox::count());
    }

    public function test_receive_does_not_error_on_a_malformed_payload(): void
    {
        $this->postJson('/api/webhooks/whatsapp', ['unexpected' => 'shape'])
            ->assertOk();

        $this->assertSame(0, ChannelInbox::count());
    }
}
