<?php

namespace App\Services;

use App\Models\AiCallLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AiSettingsService
{
    private const KEY_PROVIDER = 'ai_provider';

    private const KEY_API_KEY = 'ai_api_key';

    private const KEY_MODEL = 'ai_model';

    private const CACHE_KEY_LAST_FAILURE = 'ai_last_failure_reason';

    /**
     * How long a failure keeps the status badge red before it's treated as stale —
     * long enough to stay visible, short enough that an old, already-resolved problem
     * (e.g. a rate limit that has since reset) doesn't haunt the badge indefinitely.
     */
    private const FAILURE_TTL_MINUTES = 30;

    public const PROVIDERS = [
        'anthropic' => 'Anthropic (Claude)',
        'openai' => 'OpenAI (GPT)',
        'gemini' => 'Google Gemini (ada tingkat gratis)',
        'groq' => 'Groq (Llama/GPT-OSS, tingkat gratis besar)',
    ];

    public function provider(): ?string
    {
        return Setting::get(self::KEY_PROVIDER);
    }

    public function apiKey(): ?string
    {
        return Setting::get(self::KEY_API_KEY);
    }

    public function model(): ?string
    {
        return Setting::get(self::KEY_MODEL);
    }

    public function isConfigured(): bool
    {
        return filled($this->provider()) && filled($this->apiKey());
    }

    /**
     * Last 4 characters of the stored key, for display only — never expose the full value.
     */
    public function maskedApiKey(): ?string
    {
        $key = $this->apiKey();

        return $key ? str_repeat('•', 8).substr($key, -4) : null;
    }

    /**
     * @param  array{provider:string, api_key:?string, model:?string}  $data
     */
    public function save(array $data, User $actor): void
    {
        Setting::put(self::KEY_PROVIDER, $data['provider'], $actor->id);

        if (filled($data['api_key'])) {
            Setting::put(self::KEY_API_KEY, $data['api_key'], $actor->id);
        }

        Setting::put(self::KEY_MODEL, $data['model'] ?? null, $actor->id);
    }

    /**
     * Called after a successful AI call (scoring, reply/polish draft, or chat answer) —
     * clears any earlier failure immediately, so the badge doesn't stay red once AI
     * recovers, AND logs the call to ai_call_logs — the actual evidence the "AI Otomatis"
     * status card uses, instead of only inferring health from queue backlog + a single
     * cached last-failure reason (see AiHealthService).
     */
    public function recordSuccess(string $feature): void
    {
        Cache::forget(self::CACHE_KEY_LAST_FAILURE);

        $this->logCall($feature, 'success');
    }

    public function recordFailureFrom(Throwable $e, string $feature): void
    {
        $reason = $this->describeFailure($e);

        Cache::put(self::CACHE_KEY_LAST_FAILURE, $reason, now()->addMinutes(self::FAILURE_TTL_MINUTES));

        $this->logCall($feature, 'failure', $reason);
    }

    public function recentFailureReason(): ?string
    {
        return Cache::get(self::CACHE_KEY_LAST_FAILURE);
    }

    private function logCall(string $feature, string $outcome, ?string $reason = null): void
    {
        AiCallLog::create([
            'feature' => $feature,
            'outcome' => $outcome,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        // Cheap unbounded-growth guard — a dashboard widget only ever needs recent
        // history, no reason to keep this table growing forever under real chat volume.
        AiCallLog::where('created_at', '<', now()->subDays(30))->delete();
    }

    /**
     * A 429 is transient (quota/rate-limit that resets within seconds-to-minutes) — unlike
     * every other failure (bad key, malformed response, provider 5xx), callers may want to
     * retry instead of giving up immediately. See ScoreReportUrgencyJob.
     */
    public function isRateLimited(Throwable $e): bool
    {
        return (bool) preg_match('/API error: 429\b/', $e->getMessage());
    }

    /**
     * A 5xx or a raw connection/timeout failure (no "API error: N" match at all — see
     * describeFailure()) is a one-off provider/network hiccup, not something a retry
     * would just repeat (unlike a bad key or malformed response). Deliberately excludes
     * 429: that needs real backoff time to clear (see isRateLimited/ScoreReportUrgencyJob),
     * which a single inline retry within the same job run can't provide — callers wanting
     * an immediate one-shot retry (AnswerChatMessageWithAiJob, where a citizen is waiting
     * and re-queuing would cost a full cron cycle) should use this instead of isRateLimited.
     */
    public function isTransientFailure(Throwable $e): bool
    {
        if (preg_match('/API error: (\d+)/', $e->getMessage(), $matches)) {
            return (int) $matches[1] >= 500;
        }

        return true;
    }

    /**
     * Every provider client (App\Services\Ai\*Client) throws a RuntimeException formatted
     * as "{Provider} API error: {status} {body}" — classify by that status code so Admin
     * sees what's actually wrong instead of a generic "AI failed" message.
     */
    private function describeFailure(Throwable $e): string
    {
        if (preg_match('/API error: (\d+)/', $e->getMessage(), $matches)) {
            $status = (int) $matches[1];

            return match (true) {
                $status === 429 => 'Kuota/rate limit API AI habis — coba lagi nanti atau ganti API key.',
                in_array($status, [401, 403], true) => 'API key AI ditolak — periksa kembali di Pengaturan AI.',
                $status >= 500 => 'Layanan AI sedang bermasalah di sisi penyedia — coba lagi nanti.',
                default => "AI merespons error {$status} — coba lagi atau periksa Pengaturan AI.",
            };
        }

        return 'AI gagal merespons (koneksi/timeout) — coba lagi nanti.';
    }
}
