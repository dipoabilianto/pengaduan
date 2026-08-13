<?php

namespace App\Services;

use App\Models\AiCallLog;
use Illuminate\Support\Facades\Cache;

class AiHealthService
{
    public const HEARTBEAT_CACHE_KEY = 'ai_worker_heartbeat_at';

    /**
     * AiHeartbeatJob is scheduled every 5 minutes (routes/console.php) — twice that
     * before calling it stale tolerates one missed/delayed run without a false alarm.
     */
    private const HEARTBEAT_STALE_MINUTES = 10;

    private const RECENT_CALLS_LIMIT = 20;

    public function __construct(
        private readonly AiSettingsService $aiSettings,
        private readonly QueueHealthService $queueHealth,
    ) {
    }

    /**
     * Single source of truth for the "AI Otomatis" badge (navigation) and its polling
     * endpoint — both must agree, so the classification lives here once.
     *
     * @return array{
     *     state: 'active'|'off'|'error',
     *     reason: string,
     *     last_success_at: ?string,
     *     last_success_feature: ?string,
     *     last_failure_at: ?string,
     *     last_failure_reason: ?string,
     *     recent_success_rate: ?float,
     *     recent_calls: array<int, array{feature: string, feature_label: string, outcome: string, reason: ?string, created_at: string}>,
     * }
     */
    public function status(): array
    {
        $liveData = $this->liveData();

        if (! $this->aiSettings->isConfigured()) {
            return [...$liveData, 'state' => 'off', 'reason' => 'AI belum dikonfigurasi — laporan dinilai manual oleh Admin.'];
        }

        if ($this->queueHealth->isStuck()) {
            return [...$liveData, 'state' => 'error', 'reason' => 'Antrean macet — worker AI tampaknya berhenti memproses laporan.'];
        }

        // Distinct from "antrean macet": a worker can have zero backlog (so isStuck()
        // sees nothing wrong) while actually being dead the whole time, if nothing new
        // happened to dispatch to it — exactly what AiHeartbeatJob's periodic ping is
        // there to catch. A missing (never-yet-run) timestamp is treated as inconclusive,
        // not an error, so a fresh deploy doesn't false-alarm before the first heartbeat.
        if ($this->heartbeatIsStale()) {
            return [...$liveData, 'state' => 'error', 'reason' => 'Worker tidak merespons cek rutin dalam beberapa menit terakhir — AI mungkin berhenti bekerja diam-diam ("bengong").'];
        }

        if ($reason = $this->aiSettings->recentFailureReason()) {
            return [...$liveData, 'state' => 'error', 'reason' => $reason];
        }

        return [...$liveData, 'state' => 'active', 'reason' => 'AI aktif dan merespons normal.'];
    }

    private function heartbeatIsStale(): bool
    {
        $last = Cache::get(self::HEARTBEAT_CACHE_KEY);

        // Direct timestamp comparison rather than diffInMinutes() — avoids relying on
        // that method's sign convention (varies between Carbon major versions).
        return $last !== null && $last->lt(now()->subMinutes(self::HEARTBEAT_STALE_MINUTES));
    }

    /**
     * The actual evidence behind the badge — real recorded call outcomes, not just
     * "is a key saved" + "is the queue backlog empty". Superuser/Admin can use this to
     * tell a genuinely-idle AI (no recent questions) apart from one that LOOKS active
     * but silently fails or never actually gets called.
     */
    private function liveData(): array
    {
        $recent = AiCallLog::latest('created_at')->limit(self::RECENT_CALLS_LIMIT)->get();

        $lastSuccess = $recent->firstWhere('outcome', 'success') ?? AiCallLog::where('outcome', 'success')->latest('created_at')->first();
        $lastFailure = $recent->firstWhere('outcome', 'failure') ?? AiCallLog::where('outcome', 'failure')->latest('created_at')->first();

        return [
            'last_success_at' => $lastSuccess?->created_at?->toIso8601String(),
            'last_success_feature' => $lastSuccess ? (AiCallLog::FEATURE_LABELS[$lastSuccess->feature] ?? $lastSuccess->feature) : null,
            'last_failure_at' => $lastFailure?->created_at?->toIso8601String(),
            'last_failure_reason' => $lastFailure?->reason,
            'recent_success_rate' => $recent->isEmpty() ? null : round(100 * $recent->where('outcome', 'success')->count() / $recent->count()),
            'recent_calls' => $recent->map(fn (AiCallLog $log) => [
                'feature' => $log->feature,
                'feature_label' => AiCallLog::FEATURE_LABELS[$log->feature] ?? $log->feature,
                'outcome' => $log->outcome,
                'reason' => $log->reason,
                'created_at' => $log->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
