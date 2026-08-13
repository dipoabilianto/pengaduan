<?php

namespace Tests\Feature\Jobs;

use App\Jobs\AiHeartbeatJob;
use App\Models\AiCallLog;
use App\Models\User;
use App\Services\Ai\AiClientFactory;
use App\Services\AiHealthService;
use App\Services\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiHeartbeatJobTest extends TestCase
{
    use RefreshDatabase;

    private function configureAi(): void
    {
        app(AiSettingsService::class)->save(
            ['provider' => 'gemini', 'api_key' => 'test-key', 'model' => 'gemini-flash-latest'],
            User::factory()->create(),
        );
    }

    private function handleJob(): void
    {
        app(AiHeartbeatJob::class)->handle(app(AiClientFactory::class), app(AiSettingsService::class));
    }

    public function test_writes_the_heartbeat_timestamp_even_when_ai_is_not_configured(): void
    {
        $this->handleJob();

        $this->assertNotNull(Cache::get(AiHealthService::HEARTBEAT_CACHE_KEY));
        $this->assertSame(0, AiCallLog::count());
    }

    public function test_pings_the_configured_provider_and_logs_success(): void
    {
        $this->configureAi();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode(['text' => 'ok'])]]]],
                ],
            ]),
        ]);

        $this->handleJob();

        $this->assertNotNull(Cache::get(AiHealthService::HEARTBEAT_CACHE_KEY));
        $this->assertDatabaseHas('ai_call_logs', ['feature' => 'heartbeat', 'outcome' => 'success']);
    }

    public function test_logs_failure_when_the_provider_call_fails(): void
    {
        $this->configureAi();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('server error', 500)]);

        $this->handleJob();

        // Heartbeat cache still written — the worker itself ran fine, only the AI call failed.
        $this->assertNotNull(Cache::get(AiHealthService::HEARTBEAT_CACHE_KEY));
        $this->assertDatabaseHas('ai_call_logs', ['feature' => 'heartbeat', 'outcome' => 'failure']);
    }
}
