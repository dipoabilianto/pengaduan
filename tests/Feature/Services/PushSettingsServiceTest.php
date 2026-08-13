<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\PushSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function validServiceAccountJson(): string
    {
        return json_encode([
            'client_email' => 'fcm@sidumas-tubaba.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nfake\n-----END PRIVATE KEY-----\n",
        ]);
    }

    public function test_is_not_configured_by_default(): void
    {
        $this->assertFalse(app(PushSettingsService::class)->isConfigured());
    }

    public function test_save_persists_project_id_and_service_account(): void
    {
        $service = app(PushSettingsService::class);
        $actor = User::factory()->create();

        $service->save([
            'project_id' => 'sidumas-tubaba',
            'service_account_json' => $this->validServiceAccountJson(),
        ], $actor);

        $this->assertTrue($service->isConfigured());
        $this->assertSame('sidumas-tubaba', $service->projectId());
        $this->assertSame('fcm@sidumas-tubaba.iam.gserviceaccount.com', $service->serviceAccount()['client_email']);
    }

    public function test_is_not_configured_when_service_account_json_is_missing_required_fields(): void
    {
        $service = app(PushSettingsService::class);
        $actor = User::factory()->create();

        $service->save([
            'project_id' => 'sidumas-tubaba',
            'service_account_json' => json_encode(['foo' => 'bar']),
        ], $actor);

        $this->assertFalse($service->isConfigured());
    }

    public function test_save_with_blank_service_account_keeps_the_previous_one(): void
    {
        $service = app(PushSettingsService::class);
        $actor = User::factory()->create();

        $service->save(['project_id' => 'sidumas-tubaba', 'service_account_json' => $this->validServiceAccountJson()], $actor);
        $service->save(['project_id' => 'sidumas-tubaba', 'service_account_json' => null], $actor);

        $this->assertTrue($service->isConfigured());
    }
}
