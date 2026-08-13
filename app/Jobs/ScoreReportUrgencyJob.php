<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\Ai\AiClientFactory;
use App\Services\AiSettingsService;
use App\Services\ChatFactsService;
use App\Services\ReportAdminService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScoreReportUrgencyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Report $report)
    {
    }

    /**
     * Delay before each retry — only actually used for a rate-limited (429) attempt, see
     * handle(). Generous enough to clear a typical free-tier per-minute quota window.
     *
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [30, 90];
    }

    /**
     * Bab 3.2 & 5.3 PDR: AI scoring is a best-effort recommendation. If AI is not
     * configured or the call fails, the report must still reach Admin for manual
     * verification — it must never get stuck because of an AI/API problem.
     */
    public function handle(AiClientFactory $factory, ReportAdminService $adminService, AiSettingsService $aiSettings, ChatFactsService $facts): void
    {
        $client = $factory->make();

        if (! $client) {
            $adminService->recordAiAssessment($this->report, null);

            return;
        }

        try {
            $result = $client->assessUrgency($this->report, $facts->get());
            $adminService->recordAiAssessment($this->report, $result);
            $aiSettings->recordSuccess('urgency');
        } catch (Throwable $e) {
            // A rate limit is transient — worth an automatic retry (see backoff()) instead
            // of immediately giving up, as long as attempts remain. Every other failure
            // (bad key, malformed response, provider outage) won't be fixed by retrying,
            // so those still fall straight through to manual review below.
            if ($aiSettings->isRateLimited($e) && $this->attempts() < $this->tries) {
                throw $e;
            }

            Log::warning('AI urgency scoring failed, falling back to manual review.', [
                'report_id' => $this->report->id,
                'error' => $e->getMessage(),
            ]);

            $adminService->recordAiAssessment($this->report, null);
            $aiSettings->recordFailureFrom($e, 'urgency');
        }
    }

    /**
     * Safety net for anything that exhausts every retry (or throws outside the try/catch
     * above) — the report must still reach Admin for manual verification, never get stuck
     * silently with no assessment and no explanation.
     */
    public function failed(Throwable $exception): void
    {
        app(ReportAdminService::class)->recordAiAssessment($this->report, null);
        app(AiSettingsService::class)->recordFailureFrom($exception, 'urgency');
    }
}
