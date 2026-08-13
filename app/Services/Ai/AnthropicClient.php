<?php

namespace App\Services\Ai;

use App\Models\Report;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicClient implements AiClientInterface
{
    use BuildsUrgencyPrompt;
    use BuildsReplyPrompt;
    use BuildsPolishPrompt;
    use BuildsChatAnswerPrompt;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-5',
    ) {
    }

    public function assessUrgency(Report $report, string $facts = ''): AiAssessmentResult
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 300,
                'system' => $this->systemPrompt($facts),
                'messages' => [
                    ['role' => 'user', 'content' => $this->userPrompt($report)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('content.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Anthropic response shape: '.$response->body());
        }

        $parsed = $this->parseAssessment($text);

        return new AiAssessmentResult($parsed['score'], $parsed['flag'], $response->json() ?? [], $parsed['reasoning']);
    }

    public function generateReply(Report $report, ?string $reasoning = null): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 300,
                'temperature' => 1,
                'system' => $this->replySystemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => $this->replyUserPrompt($report, $reasoning)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('content.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Anthropic response shape: '.$response->body());
        }

        return $this->parseReply($text);
    }

    public function polishText(string $draft): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 300,
                'system' => $this->polishSystemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => $this->polishUserPrompt($draft)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('content.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Anthropic response shape: '.$response->body());
        }

        return $this->parsePolish($text);
    }

    public function answerChatMessage(array $context): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 300,
                'system' => $this->chatAnswerSystemPrompt($context['facts'] ?? ''),
                'messages' => [
                    ['role' => 'user', 'content' => $this->chatAnswerUserPrompt($context)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('content.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Anthropic response shape: '.$response->body());
        }

        return $this->parseChatAnswer($text);
    }
}
