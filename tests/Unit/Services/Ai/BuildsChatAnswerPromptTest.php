<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\BuildsChatAnswerPrompt;
use PHPUnit\Framework\TestCase;

class BuildsChatAnswerPromptTest extends TestCase
{
    private function builder(): object
    {
        return new class
        {
            use BuildsChatAnswerPrompt;

            public function userPrompt(array $context): string
            {
                return $this->chatAnswerUserPrompt($context);
            }
        };
    }

    public function test_highlights_the_most_recent_citizen_message_separately(): void
    {
        $prompt = $this->builder()->userPrompt([
            'history' => [
                ['role' => 'citizen', 'body' => 'kasih pantun perpisahan'],
                ['role' => 'assistant', 'body' => 'Jalan-jalan beli buah duku...'],
                ['role' => 'citizen', 'body' => 'persyaratan buat kk apa ya?'],
            ],
            'related_report' => null,
        ]);

        $this->assertStringContainsString('PESAN TERBARU PELAPOR', $prompt);
        $this->assertStringContainsString('"persyaratan buat kk apa ya?"', $prompt);
    }

    public function test_last_citizen_message_is_the_true_latest_even_after_multiple_assistant_turns(): void
    {
        $prompt = $this->builder()->userPrompt([
            'history' => [
                ['role' => 'citizen', 'body' => 'hai kak'],
                ['role' => 'assistant', 'body' => 'balasan lama 1'],
                ['role' => 'assistant', 'body' => 'balasan lama 2'],
                ['role' => 'citizen', 'body' => 'kak aku blm mau selesai'],
            ],
            'related_report' => null,
        ]);

        $this->assertStringContainsString('"kak aku blm mau selesai"', $prompt);
        $this->assertStringNotContainsString('"hai kak"', explode('PESAN TERBARU PELAPOR', $prompt)[1]);
    }

    public function test_instructs_checking_for_unanswered_earlier_questions(): void
    {
        $prompt = $this->builder()->userPrompt([
            'history' => [['role' => 'citizen', 'body' => 'halo']],
            'related_report' => null,
        ]);

        $this->assertStringContainsString('belum terjawab', $prompt);
    }
}
