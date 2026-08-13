<?php

namespace Tests\Feature\Services;

use App\Models\AiCallLog;
use App\Models\User;
use App\Services\AiHealthService;
use App\Services\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function configureAi(): void
    {
        app(AiSettingsService::class)->save(
            ['provider' => 'gemini', 'api_key' => 'test-key', 'model' => 'gemini-flash-latest'],
            User::factory()->create(),
        );
    }

    public function test_state_is_off_when_not_configured_regardless_of_call_history(): void
    {
        $status = app(AiHealthService::class)->status();

        $this->assertSame('off', $status['state']);
    }

    public function test_state_is_active_with_no_call_history_yet(): void
    {
        $this->configureAi();

        $status = app(AiHealthService::class)->status();

        $this->assertSame('active', $status['state']);
        $this->assertNull($status['recent_success_rate']);
        $this->assertNull($status['last_success_at']);
    }

    public function test_missing_heartbeat_is_not_treated_as_an_error_on_a_fresh_install(): void
    {
        $this->configureAi();
        Cache::forget(AiHealthService::HEARTBEAT_CACHE_KEY);

        $status = app(AiHealthService::class)->status();

        $this->assertSame('active', $status['state']);
    }

    public function test_state_is_error_when_the_heartbeat_is_stale(): void
    {
        $this->configureAi();
        Cache::put(AiHealthService::HEARTBEAT_CACHE_KEY, now()->subMinutes(15), now()->addMinutes(15));

        $status = app(AiHealthService::class)->status();

        $this->assertSame('error', $status['state']);
        $this->assertStringContainsString('bengong', $status['reason']);
    }

    public function test_state_is_active_when_the_heartbeat_is_recent(): void
    {
        $this->configureAi();
        Cache::put(AiHealthService::HEARTBEAT_CACHE_KEY, now()->subMinutes(2), now()->addMinutes(15));

        $status = app(AiHealthService::class)->status();

        $this->assertSame('active', $status['state']);
    }

    public function test_reports_last_success_and_last_failure_with_timestamps_and_labels(): void
    {
        $this->configureAi();
        AiCallLog::create(['feature' => 'urgency', 'outcome' => 'success', 'created_at' => now()->subMinutes(10)]);
        AiCallLog::create(['feature' => 'chat', 'outcome' => 'failure', 'reason' => 'Kuota habis.', 'created_at' => now()->subMinutes(5)]);

        $status = app(AiHealthService::class)->status();

        $this->assertNotNull($status['last_success_at']);
        $this->assertSame('Penilaian Urgensi', $status['last_success_feature']);
        $this->assertNotNull($status['last_failure_at']);
        $this->assertSame('Kuota habis.', $status['last_failure_reason']);
    }

    public function test_computes_recent_success_rate_from_last_twenty_calls(): void
    {
        $this->configureAi();
        for ($i = 0; $i < 3; $i++) {
            AiCallLog::create(['feature' => 'chat', 'outcome' => 'success', 'created_at' => now()->subMinutes($i)]);
        }
        AiCallLog::create(['feature' => 'chat', 'outcome' => 'failure', 'created_at' => now()]);

        $status = app(AiHealthService::class)->status();

        $this->assertSame(75.0, $status['recent_success_rate']);
        $this->assertCount(4, $status['recent_calls']);
    }

    public function test_a_failed_call_still_surfaces_as_an_error_state(): void
    {
        $this->configureAi();
        app(AiSettingsService::class)->recordFailureFrom(new \RuntimeException('Gemini API error: 429 {}'), 'chat');

        $status = app(AiHealthService::class)->status();

        $this->assertSame('error', $status['state']);
    }
}
