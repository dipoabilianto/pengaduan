<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ScoreReportUrgencyJob;
use App\Models\Report;
use App\Models\User;
use App\Services\Ai\AiClientFactory;
use App\Services\AiSettingsService;
use App\Services\ChatFactsService;
use App\Services\ReportAdminService;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ScoreReportUrgencyJobTest extends TestCase
{
    use RefreshDatabase;

    private function configureAi(): void
    {
        app(AiSettingsService::class)->save(
            ['provider' => 'gemini', 'api_key' => 'test-key', 'model' => 'gemini-flash-latest'],
            User::factory()->create(),
        );
    }

    private function report(array $attributes = []): Report
    {
        return Report::create(array_merge([
            'ticket_no' => 'TCK-'.fake()->unique()->numerify('####'),
            'type' => 'pengaduan',
            'category' => 'Pelayanan Administrasi Kependudukan',
            'channel' => 'web',
            'status' => 'baru_masuk',
            'what' => 'Contoh kronologi kejadian untuk pengujian.',
        ], $attributes));
    }

    private function fakeGeminiAssessment(array $payload): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($payload)]]]],
                ],
            ]),
        ]);
    }

    private function handleJob(ScoreReportUrgencyJob $job): void
    {
        $job->handle(
            app(AiClientFactory::class),
            app(ReportAdminService::class),
            app(AiSettingsService::class),
            app(ChatFactsService::class),
        );
    }

    /**
     * The urgency-scoring prompt now carries the same Superuser-curated office knowledge
     * as the chat assistant (ChatFactsService) as extra context — the outgoing request must
     * actually include it, not just default facts silently ignored.
     */
    public function test_urgency_assessment_reads_the_chat_facts_knowledge_base_as_context(): void
    {
        $this->configureAi();
        app(ChatFactsService::class)->save('Jam layanan: Senin-Jumat 08.00-15.00 WIB, Sabtu-Minggu tutup.');
        $report = $this->report();
        $this->fakeGeminiAssessment(['flag' => 'sedang', 'score' => 50, 'reasoning' => 'Keluhan layanan standar.']);

        $this->handleJob(new ScoreReportUrgencyJob($report));

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'Jam layanan: Senin-Jumat 08.00-15.00 WIB, Sabtu-Minggu tutup.')
                && str_contains($body, 'REFERENSI TAMBAHAN');
        });
        $this->assertDatabaseHas('report_ai_assessments', [
            'report_id' => $report->id,
            'ai_suggested_flag' => 'sedang',
        ]);
    }

    public function test_still_assesses_normally_when_no_custom_facts_have_been_saved(): void
    {
        $this->configureAi();
        $report = $this->report();
        $this->fakeGeminiAssessment(['flag' => 'tinggi', 'score' => 75, 'reasoning' => 'Dampak signifikan.']);

        $this->handleJob(new ScoreReportUrgencyJob($report));

        $this->assertDatabaseHas('report_ai_assessments', [
            'report_id' => $report->id,
            'ai_suggested_flag' => 'tinggi',
        ]);
    }

    private function fakeRateLimitedGemini(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
        ]);
    }

    /**
     * A 429 is transient — with attempts still remaining (default attempts() is 1 when the
     * job isn't attached to a real queue Job instance, well under $tries=3), the job must
     * let the exception propagate so Laravel's queue retries it (per backoff()) instead of
     * silently giving up on the first try.
     */
    public function test_retries_automatically_on_a_rate_limited_response_instead_of_giving_up(): void
    {
        $this->configureAi();
        $report = $this->report();
        $this->fakeRateLimitedGemini();

        $this->expectException(RuntimeException::class);

        $this->handleJob(new ScoreReportUrgencyJob($report));
    }

    /**
     * Once every retry is exhausted, a still-rate-limited report must fall back to manual
     * review like any other failure — never left permanently unassessed.
     */
    public function test_falls_back_to_manual_review_once_retries_are_exhausted_on_rate_limit(): void
    {
        $this->configureAi();
        $report = $this->report();
        $this->fakeRateLimitedGemini();

        $job = new ScoreReportUrgencyJob($report);
        $queueJob = \Mockery::mock(QueueJobContract::class);
        $queueJob->shouldReceive('attempts')->andReturn(3);
        $job->setJob($queueJob);

        $this->handleJob($job);

        $this->assertDatabaseMissing('report_ai_assessments', ['report_id' => $report->id]);
        $this->assertDatabaseHas('ai_call_logs', ['feature' => 'urgency', 'outcome' => 'failure']);
    }

    /**
     * A non-rate-limit failure (bad API key, malformed response, provider outage) won't be
     * fixed by retrying — it must fall back to manual review immediately, on the very first
     * attempt, not wait for retries to exhaust.
     */
    public function test_does_not_retry_a_non_rate_limit_failure(): void
    {
        $this->configureAi();
        $report = $this->report();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'invalid api key']], 401),
        ]);

        $this->handleJob(new ScoreReportUrgencyJob($report));

        $this->assertDatabaseMissing('report_ai_assessments', ['report_id' => $report->id]);
        $this->assertDatabaseHas('ai_call_logs', ['feature' => 'urgency', 'outcome' => 'failure']);
    }

    /**
     * Safety net: if the job is ever marked failed outright (every retry exhausted via the
     * real queue worker, or an exception thrown outside the try/catch in handle()), the
     * report must still reach Admin for manual review rather than being stuck with no
     * assessment and no explanation at all.
     */
    public function test_failed_hook_records_a_null_assessment_as_a_last_resort(): void
    {
        $report = $this->report();

        (new ScoreReportUrgencyJob($report))->failed(new RuntimeException('Gemini API error: 429 quota exceeded'));

        $this->assertDatabaseMissing('report_ai_assessments', ['report_id' => $report->id]);
        $this->assertDatabaseHas('ai_call_logs', ['feature' => 'urgency', 'outcome' => 'failure']);
    }
}
