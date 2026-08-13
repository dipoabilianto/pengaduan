<?php

namespace App\Services\Ai;

use App\Services\AiSettingsService;

class AiClientFactory
{
    public function __construct(private readonly AiSettingsService $settings)
    {
    }

    /**
     * Returns null when AI is not configured — callers must treat this as
     * "skip AI scoring, fall back to manual admin review" per Bab 5.3 PDR,
     * never as an error.
     */
    public function make(): ?AiClientInterface
    {
        if (! $this->settings->isConfigured()) {
            return null;
        }

        $apiKey = $this->settings->apiKey();
        $model = $this->settings->model();

        return match ($this->settings->provider()) {
            'anthropic' => new AnthropicClient($apiKey, $model ?: 'claude-sonnet-5'),
            'openai' => new OpenAiClient($apiKey, $model ?: 'gpt-4o-mini'),
            'gemini' => new GeminiClient($apiKey, $model ?: 'gemini-2.0-flash'),
            'groq' => new GroqClient($apiKey, $model ?: 'openai/gpt-oss-120b'),
            default => null,
        };
    }
}
