<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\NormalizesAiText;
use PHPUnit\Framework\TestCase;

class NormalizesAiTextTest extends TestCase
{
    use NormalizesAiText;

    public function test_leaves_clean_single_line_text_unchanged(): void
    {
        $this->assertSame('Kantor buka Senin-Jumat.', $this->normalizeAiText('Kantor buka Senin-Jumat.'));
    }

    public function test_trims_leading_and_trailing_whitespace(): void
    {
        $this->assertSame('Halo.', $this->normalizeAiText("  \nHalo.  \n\n"));
    }

    public function test_collapses_three_or_more_blank_lines_to_one(): void
    {
        $this->assertSame(
            "Paragraf satu.\n\nParagraf dua.",
            $this->normalizeAiText("Paragraf satu.\n\n\n\n\nParagraf dua."),
        );
    }

    public function test_keeps_a_single_intentional_paragraph_break(): void
    {
        $this->assertSame(
            "Paragraf satu.\n\nParagraf dua.",
            $this->normalizeAiText("Paragraf satu.\n\nParagraf dua."),
        );
    }

    public function test_collapses_runs_of_spaces_within_a_line(): void
    {
        $this->assertSame('Halo dunia.', $this->normalizeAiText('Halo    dunia.'));
    }

    public function test_strips_trailing_spaces_before_a_newline(): void
    {
        $this->assertSame("Baris satu.\nBaris dua.", $this->normalizeAiText("Baris satu.   \nBaris dua."));
    }

    public function test_normalizes_crlf_line_endings(): void
    {
        $this->assertSame("Baris satu.\n\nBaris dua.", $this->normalizeAiText("Baris satu.\r\n\r\nBaris dua."));
    }
}
