<?php

namespace App\Jobs;

use App\Services\Ai\AiClientFactory;
use App\Services\AiHealthService;
use App\Services\AiSettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Scheduled every few minutes (routes/console.php) so the "AI Otomatis" status card
 * reflects reality even during a lull in real citizen/admin activity — without this,
 * a dead queue worker with an empty queue looks identical to a healthy idle one (no
 * backlog for QueueHealthService::isStuck() to catch), and a revoked/expired API key
 * would only surface the next time a real citizen happens to message the chat bot.
 *
 * Two independent signals, both written unconditionally:
 * 1. The cache heartbeat timestamp proves the queue worker + scheduler are alive at
 *    all — written even if AI isn't configured, since that's infra health, not AI health.
 * 2. The actual AI call (reusing polishText() as the cheapest existing real capability —
 *    trivial input/output, no domain data needed, deliberately NOT a bespoke ping method
 *    just for this) proves the configured provider is genuinely reachable and responding,
 *    logged to ai_call_logs under the 'heartbeat' feature so it's visible in the recent
 *    activity list without being confused for a real officer-triggered polish.
 */
class AiHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(AiClientFactory $factory, AiSettingsService $aiSettings): void
    {
        Cache::put(AiHealthService::HEARTBEAT_CACHE_KEY, now(), now()->addMinutes(15));

        $client = $factory->make();

        if (! $client) {
            return;
        }

        try {
            $client->polishText('Halo, ini pemeriksaan rutin sistem.');
            $aiSettings->recordSuccess('heartbeat');
        } catch (Throwable $e) {
            $aiSettings->recordFailureFrom($e, 'heartbeat');
        }
    }
}
