<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\BuildsUrgencyPrompt;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BuildsUrgencyPromptTest extends TestCase
{
    private function parser(): object
    {
        return new class
        {
            use BuildsUrgencyPrompt;

            public function parse(string $text): array
            {
                return $this->parseAssessment($text);
            }

            public function prompt(string $facts = ''): string
            {
                return $this->systemPrompt($facts);
            }
        };
    }

    public function test_system_prompt_omits_facts_block_when_no_facts_given(): void
    {
        $prompt = $this->parser()->prompt();

        $this->assertStringNotContainsString('REFERENSI TAMBAHAN', $prompt);
    }

    public function test_system_prompt_includes_facts_as_context_when_given(): void
    {
        $prompt = $this->parser()->prompt('Jam layanan: Senin-Jumat 08.00-15.00.');

        $this->assertStringContainsString('REFERENSI TAMBAHAN', $prompt);
        $this->assertStringContainsString('Jam layanan: Senin-Jumat 08.00-15.00.', $prompt);
        // Must read as context, never as a rule that can override the fixed flag criteria.
        $this->assertStringContainsString('TIDAK BOLEH', $prompt);
    }

    public function test_parses_valid_json_response(): void
    {
        $result = $this->parser()->parse(
            '{"flag": "tinggi", "score": 72, "reasoning": "Dampak signifikan pada layanan."}'
        );

        $this->assertSame('tinggi', $result['flag']);
        $this->assertSame(72, $result['score']);
        $this->assertSame('Dampak signifikan pada layanan.', $result['reasoning']);
    }

    public function test_strips_markdown_code_fences(): void
    {
        $result = $this->parser()->parse(
            "```json\n{\"flag\": \"rendah\", \"score\": 10, \"reasoning\": \"Masukan ringan.\"}\n```"
        );

        $this->assertSame('rendah', $result['flag']);
        $this->assertSame(10, $result['score']);
    }

    public function test_clamps_score_above_100(): void
    {
        $result = $this->parser()->parse('{"flag": "sedang", "score": 150}');

        $this->assertSame(100, $result['score']);
    }

    public function test_clamps_score_below_0(): void
    {
        $result = $this->parser()->parse('{"flag": "sedang", "score": -20}');

        $this->assertSame(0, $result['score']);
    }

    public function test_defaults_reasoning_to_empty_string_when_missing(): void
    {
        $result = $this->parser()->parse('{"flag": "sedang", "score": 50}');

        $this->assertSame('', $result['reasoning']);
    }

    public function test_accepts_tidak_valid_flag(): void
    {
        $result = $this->parser()->parse(
            '{"flag": "tidak_valid", "score": 5, "reasoning": "Isi tidak masuk akal, tidak ada detail sama sekali."}'
        );

        $this->assertSame('tidak_valid', $result['flag']);
    }

    public function test_throws_on_unknown_flag(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parser()->parse('{"flag": "urgent", "score": 90}');
    }

    public function test_throws_on_non_json_response(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parser()->parse('Maaf, saya tidak bisa menilai laporan ini.');
    }

    public function test_throws_when_required_fields_missing(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parser()->parse('{"reasoning": "tanpa flag dan score"}');
    }
}
