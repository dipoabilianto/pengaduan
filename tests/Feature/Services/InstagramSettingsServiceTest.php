<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\InstagramSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstagramSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_not_configured_by_default(): void
    {
        $this->assertFalse(app(InstagramSettingsService::class)->isConfigured());
        $this->assertNull(app(InstagramSettingsService::class)->maskedAccessToken());
    }

    public function test_save_persists_all_fields_and_marks_configured(): void
    {
        $service = app(InstagramSettingsService::class);
        $actor = User::factory()->create();

        $service->save([
            'business_account_id' => '17841400000000000',
            'access_token' => 'EAABsecrettoken1234',
            'webhook_verify_token' => 'my-verify-token',
        ], $actor);

        $this->assertTrue($service->isConfigured());
        $this->assertSame('17841400000000000', $service->businessAccountId());
        $this->assertSame('my-verify-token', $service->webhookVerifyToken());
        $this->assertStringEndsWith('1234', $service->maskedAccessToken());
        $this->assertStringNotContainsString('EAABsecrettoken', $service->maskedAccessToken());
    }

    public function test_save_with_blank_access_token_keeps_the_previous_one(): void
    {
        $service = app(InstagramSettingsService::class);
        $actor = User::factory()->create();

        $service->save([
            'business_account_id' => '17841400000000000',
            'access_token' => 'original-token',
            'webhook_verify_token' => 'my-verify-token',
        ], $actor);

        $service->save([
            'business_account_id' => '17841400000000000',
            'access_token' => null,
            'webhook_verify_token' => 'my-verify-token',
        ], $actor);

        $this->assertSame('original-token', $service->accessToken());
    }
}
