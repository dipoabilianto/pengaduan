<?php

namespace App\Services\Ai;

use App\Models\Report;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient implements AiClientInterface
{
    use BuildsUrgencyPrompt;
    use BuildsReplyPrompt;
    use BuildsPolishPrompt;
    use BuildsChatAnswerPrompt;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.0-flash',
    ) {
    }

    public function assessUrgency(Report $report, string $facts = ''): AiAssessmentResult
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $response = Http::timeout(30)
            ->post($url.'?key='.$this->apiKey, [
                'system_instruction' => [
                    'parts' => [['text' => $this->systemPrompt($facts)]],
                ],
                'contents' => [
                    ['parts' => [['text' => $this->userPrompt($report)]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Gemini response shape: '.$response->body());
        }

        $parsed = $this->parseAssessment($text);

        return new AiAssessmentResult($parsed['score'], $parsed['flag'], $response->json() ?? [], $parsed['reasoning']);
    }

    public function generateReply(Report $report, ?string $reasoning = null): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $response = Http::timeout(30)
            ->post($url.'?key='.$this->apiKey, [
                'system_instruction' => [
                    'parts' => [['text' => $this->replySystemPrompt()]],
                ],
                'contents' => [
                    ['parts' => [['text' => $this->replyUserPrompt($report, $reasoning)]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 1,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Gemini response shape: '.$response->body());
        }

        return $this->parseReply($text);
    }

    public function polishText(string $draft): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $response = Http::timeout(30)
            ->post($url.'?key='.$this->apiKey, [
                'system_instruction' => [
                    'parts' => [['text' => $this->polishSystemPrompt()]],
                ],
                'contents' => [
                    ['parts' => [['text' => $this->polishUserPrompt($draft)]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Gemini response shape: '.$response->body());
        }

        return $this->parsePolish($text);
    }

    public function answerChatMessage(array $context): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $response = Http::timeout(30)
            ->post($url.'?key='.$this->apiKey, [
                'system_instruction' => [
                    'parts' => [['text' => $this->chatAnswerSystemPrompt($context['facts'] ?? '')]],
                ],
                'contents' => [
                    ['parts' => [['text' => $this->chatAnswerUserPrompt($context)]]],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->status().' '.$response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Gemini response shape: '.$response->body());
        }

        return $this->parseChatAnswer($text);
    }
}
