<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_not_configured_by_default(): void
    {
        $this->assertFalse(app(WhatsAppSettingsService::class)->isConfigured());
        $this->assertNull(app(WhatsAppSettingsService::class)->maskedAccessToken());
    }

    public function test_save_persists_all_fields_and_marks_configured(): void
    {
        $service = app(WhatsAppSettingsService::class);
        $actor = User::factory()->create();

        $service->save([
            'phone_number_id' => '109876543210987',
            'access_token' => 'EAABsecrettoken1234',
            'webhook_verify_token' => 'my-verify-token',
        ], $actor);

        $this->assertTrue($service->isConfigured());
        $this->assertSame('109876543210987', $service->phoneNumberId());
        $this->assertSame('my-verify-token', $service->webhookVerifyToken());
        $this->assertStringEndsWith('1234', $service->maskedAccessToken());
        $this->assertStringNotContainsString('EAABsecrettoken', $service->maskedAccessToken());
    }

    public function test_save_with_blank_access_token_keeps_the_previous_one(): void
    {
        $service = app(WhatsAppSettingsService::class);
        $actor = User::factory()->create();

        $service->save([
            'phone_number_id' => '109876543210987',
            'access_token' => 'original-token',
            'webhook_verify_token' => 'my-verify-token',
        ], $actor);

        $service->save([
            'phone_number_id' => '109876543210987',
            'access_token' => null,
            'webhook_verify_token' => 'my-verify-token',
        ], $actor);

        $this->assertSame('original-token', $service->accessToken());
    }
}
