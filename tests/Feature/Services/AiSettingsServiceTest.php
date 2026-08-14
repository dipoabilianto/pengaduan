<?php

namespace Tests\Feature\Services;

use App\Models\AiCallLog;
use App\Services\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AiSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_failure_reason_is_null_when_nothing_has_failed(): void
    {
        $this->assertNull(app(AiSettingsService::class)->recentFailureReason());
    }

    public function test_classifies_quota_error_as_rate_limit(): void
    {
        $service = app(AiSettingsService::class);

        $service->recordFailureFrom(new RuntimeException('Gemini API error: 429 {"error":"quota"}'), 'urgency');

        $this->assertStringContainsString('Kuota', $service->recentFailureReason());
    }

    public function test_classifies_unauthorized_error_as_invalid_api_key(): void
    {
        $service = app(AiSettingsService::class);

        $service->recordFailureFrom(new RuntimeException('OpenAI API error: 401 {"error":"unauthorized"}'), 'urgency');

        $this->assertStringContainsString('API key AI ditolak', $service->recentFailureReason());
    }

    public function test_classifies_forbidden_error_as_invalid_api_key(): void
    {
        $service = app(AiSettingsService::class);

        $service->recordFailureFrom(new RuntimeException('Anthropic API error: 403 {"error":"forbidden"}'), 'urgency');

        $this->assertStringContainsString('API key AI ditolak', $service->recentFailureReason());
    }

    public function test_classifies_server_error_as_provider_side_issue(): void
    {
        $service = app(AiSettingsService::class);

        $service->recordFailureFrom(new RuntimeException('Gemini API error: 503 {"error":"unavailable"}'), 'urgency');

        $this->assertStringContainsString('penyedia', $service->recentFailureReason());
    }

    public function test_classifies_unrecognised_error_as_generic_connection_failure(): void
    {
        $service = app(AiSettingsService::class);

        $service->recordFailureFrom(new RuntimeException('cURL error 28: Operation timed out'), 'urgency');

        $this->assertStringContainsString('koneksi/timeout', $service->recentFailureReason());
    }

    public function test_transient_failure_covers_server_errors_and_unrecognised_connection_issues(): void
    {
        $service = app(AiSettingsService::class);

        $this->assertTrue($service->isTransientFailure(new RuntimeException('Gemini API error: 503 {"error":"unavailable"}')));
        $this->assertTrue($service->isTransientFailure(new RuntimeException('cURL error 28: Operation timed out')));
    }

    public function test_transient_failure_excludes_rate_limits_and_client_errors(): void
    {
        $service = app(AiSettingsService::class);

        $this->assertFalse($service->isTransientFailure(new RuntimeException('Gemini API error: 429 {"error":"quota"}')));
        $this->assertFalse($service->isTransientFailure(new RuntimeException('OpenAI API error: 401 {"error":"unauthorized"}')));
    }

    public function test_record_success_clears_a_previous_failure(): void
    {
        $service = app(AiSettingsService::class);

        $service->recordFailureFrom(new RuntimeException('Gemini API error: 429 {}'), 'urgency');
        $this->assertNotNull($service->recentFailureReason());

        $service->recordSuccess('urgency');

        $this->assertNull($service->recentFailureReason());
    }

    public function test_record_success_logs_an_ai_call_log_row(): void
    {
        app(AiSettingsService::class)->recordSuccess('chat');

        $this->assertDatabaseHas('ai_call_logs', ['feature' => 'chat', 'outcome' => 'success', 'reason' => null]);
    }

    public function test_record_failure_logs_an_ai_call_log_row_with_the_classified_reason(): void
    {
        app(AiSettingsService::class)->recordFailureFrom(new RuntimeException('Gemini API error: 429 {}'), 'chat');

        $log = AiCallLog::where('feature', 'chat')->where('outcome', 'failure')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Kuota', $log->reason);
    }

    public function test_old_call_logs_are_pruned_after_thirty_days(): void
    {
        AiCallLog::create(['feature' => 'chat', 'outcome' => 'success', 'created_at' => now()->subDays(31)]);

        app(AiSettingsService::class)->recordSuccess('chat');

        $this->assertSame(1, AiCallLog::count());
    }
}
