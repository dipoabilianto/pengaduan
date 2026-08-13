<?php

namespace Tests\Unit\Services\Ai;

use App\Models\User;
use App\Services\Ai\AiClientFactory;
use App\Services\Ai\AnthropicClient;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GroqClient;
use App\Services\Ai\OpenAiClient;
use App\Services\AiSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiClientFactoryTest extends TestCase
{
    use RefreshDatabase;

    private function configure(string $provider): void
    {
        app(AiSettingsService::class)->save(
            ['provider' => $provider, 'api_key' => 'test-key', 'model' => null],
            User::factory()->create(),
        );
    }

    public function test_returns_null_when_ai_is_not_configured(): void
    {
        $this->assertNull(app(AiClientFactory::class)->make());
    }

    public function test_resolves_anthropic_client(): void
    {
        $this->configure('anthropic');

        $this->assertInstanceOf(AnthropicClient::class, app(AiClientFactory::class)->make());
    }

    public function test_resolves_openai_client(): void
    {
        $this->configure('openai');

        $this->assertInstanceOf(OpenAiClient::class, app(AiClientFactory::class)->make());
    }

    public function test_resolves_gemini_client(): void
    {
        $this->configure('gemini');

        $this->assertInstanceOf(GeminiClient::class, app(AiClientFactory::class)->make());
    }

    public function test_resolves_groq_client(): void
    {
        $this->configure('groq');

        $this->assertInstanceOf(GroqClient::class, app(AiClientFactory::class)->make());
    }
}
