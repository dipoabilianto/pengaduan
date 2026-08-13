<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Models\ChannelInbox;
use App\Models\User;
use App\Services\InstagramSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function configureVerifyToken(string $token = 'my-verify-token'): void
    {
        app(InstagramSettingsService::class)->save([
            'business_account_id' => '17841400000000000',
            'access_token' => 'fake-token',
            'webhook_verify_token' => $token,
        ], User::factory()->create());
    }

    public function test_verify_succeeds_with_the_correct_token(): void
    {
        $this->configureVerifyToken('correct-token');

        $this->get('/api/webhooks/instagram?hub_mode=subscribe&hub_verify_token=correct-token&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_verify_fails_with_the_wrong_token(): void
    {
        $this->configureVerifyToken('correct-token');

        $this->get('/api/webhooks/instagram?hub_mode=subscribe&hub_verify_token=wrong-token&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_verify_fails_when_not_configured(): void
    {
        $this->get('/api/webhooks/instagram?hub_mode=subscribe&hub_verify_token=anything&hub_challenge=12345')
            ->assertForbidden();
    }

    public function test_receive_creates_a_row_from_a_dm_payload(): void
    {
        $payload = [
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => '1234567890'],
                            'message' => ['mid' => 'mid.abc', 'text' => 'Halo, ada pertanyaan soal KTP'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/instagram', $payload)->assertOk();

        $this->assertDatabaseHas('channel_inbox', [
            'source' => 'instagram',
            'external_type' => 'message',
            'external_ref' => '1234567890',
            'external_id' => 'mid.abc',
            'raw_message' => 'Halo, ada pertanyaan soal KTP',
            'status' => 'baru',
        ]);
    }

    public function test_receive_skips_echoed_and_non_message_events(): void
    {
        $payload = [
            'entry' => [
                [
                    'messaging' => [
                        ['sender' => ['id' => '1'], 'message' => ['mid' => 'm1', 'text' => 'pesan bisnis sendiri', 'is_echo' => true]],
                        ['sender' => ['id' => '2'], 'read' => ['mid' => 'm2']],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/instagram', $payload)->assertOk();

        $this->assertSame(0, ChannelInbox::count());
    }

    public function test_receive_creates_a_row_from_a_comment_payload(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'comments',
                            'value' => [
                                'id' => 'comment-1',
                                'text' => 'Kok pelayanannya lama sekali',
                                'from' => ['id' => '999', 'username' => 'warga_tubaba'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/instagram', $payload)->assertOk();

        $this->assertDatabaseHas('channel_inbox', [
            'source' => 'instagram',
            'external_type' => 'comment',
            'external_ref' => 'warga_tubaba',
            'external_id' => 'comment-1',
            'raw_message' => 'Kok pelayanannya lama sekali',
            'status' => 'baru',
        ]);
    }

    public function test_receive_fetches_and_creates_a_row_from_a_mention_payload(): void
    {
        $this->configureVerifyToken();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'mentioned_comment' => ['text' => 'Menyebut akun dinas di komentar ini'],
            ]),
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        ['field' => 'mentions', 'value' => ['comment_id' => 'comment-42']],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/instagram', $payload)->assertOk();

        $this->assertDatabaseHas('channel_inbox', [
            'source' => 'instagram',
            'external_type' => 'comment',
            'external_id' => 'comment-42',
            'raw_message' => 'Menyebut akun dinas di komentar ini',
            'status' => 'baru',
        ]);
    }

    public function test_receive_falls_back_to_a_placeholder_when_mention_fetch_fails(): void
    {
        $this->configureVerifyToken();
        Http::fake(['graph.facebook.com/*' => Http::response([], 500)]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        ['field' => 'mentions', 'value' => ['comment_id' => 'comment-99']],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/instagram', $payload)->assertOk();

        $row = ChannelInbox::where('external_id', 'comment-99')->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString('gagal mengambil isi', $row->raw_message);
    }

    public function test_receive_handles_multiple_items_across_dm_and_comments_in_one_payload(): void
    {
        $payload = [
            'entry' => [
                [
                    'messaging' => [
                        ['sender' => ['id' => '1'], 'message' => ['mid' => 'm1', 'text' => 'DM satu']],
                    ],
                    'changes' => [
                        ['field' => 'comments', 'value' => ['id' => 'c1', 'text' => 'Komentar satu', 'from' => ['id' => '2']]],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/webhooks/instagram', $payload)->assertOk();

        $this->assertSame(2, ChannelInbox::count());
    }

    public function test_receive_does_not_error_on_a_malformed_payload(): void
    {
        $this->postJson('/api/webhooks/instagram', ['unexpected' => 'shape'])
            ->assertOk();

        $this->assertSame(0, ChannelInbox::count());
    }
}
