<?php

namespace App\Services\Ai;

use App\Models\Report;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Groq's API is OpenAI-compatible (chat completions, response_format, message shape) —
 * only the base URL and default model differ. Chosen for its generous free tier
 * (~1,000 requests/day, no billing required) compared to Gemini's/OpenAI's stingier limits.
 */
class GroqClient implements AiClientInterface
{
    use BuildsUrgencyPrompt;
    use BuildsReplyPrompt;
    use BuildsPolishPrompt;
    use BuildsChatAnswerPrompt;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'openai/gpt-oss-120b',
    ) {
    }

    public function assessUrgency(Report $report, string $facts = ''): AiAssessmentResult
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($facts)],
                    ['role' => 'user', 'content' => $this->userPrompt($report)],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Groq API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Groq response shape: '.$response->body());
        }

        $parsed = $this->parseAssessment($text);

        return new AiAssessmentResult($parsed['score'], $parsed['flag'], $response->json() ?? [], $parsed['reasoning']);
    }

    public function generateReply(Report $report, ?string $reasoning = null): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->replySystemPrompt()],
                    ['role' => 'user', 'content' => $this->replyUserPrompt($report, $reasoning)],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 1,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Groq API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Groq response shape: '.$response->body());
        }

        return $this->parseReply($text);
    }

    public function polishText(string $draft): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->polishSystemPrompt()],
                    ['role' => 'user', 'content' => $this->polishUserPrompt($draft)],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Groq API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Groq response shape: '.$response->body());
        }

        return $this->parsePolish($text);
    }

    public function answerChatMessage(array $context): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->chatAnswerSystemPrompt($context['facts'] ?? '')],
                    ['role' => 'user', 'content' => $this->chatAnswerUserPrompt($context)],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Groq API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Groq response shape: '.$response->body());
        }

        return $this->parseChatAnswer($text);
    }
}
