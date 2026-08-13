<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\BuildsReplyPrompt;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BuildsReplyPromptTest extends TestCase
{
    private function parser(): object
    {
        return new class
        {
            use BuildsReplyPrompt;

            public function parse(string $text): string
            {
                return $this->parseReply($text);
            }
        };
    }

    public function test_parses_valid_json_response(): void
    {
        $result = $this->parser()->parse('{"reply": "Terima kasih atas laporannya."}');

        $this->assertSame('Terima kasih atas laporannya.', $result);
    }

    public function test_strips_markdown_code_fences(): void
    {
        $result = $this->parser()->parse("```json\n{\"reply\": \"Mohon maaf, laporan belum bisa kami tindak lanjuti.\"}\n```");

        $this->assertSame('Mohon maaf, laporan belum bisa kami tindak lanjuti.', $result);
    }

    public function test_trims_whitespace_around_reply(): void
    {
        $result = $this->parser()->parse('{"reply": "  Terima kasih.  "}');

        $this->assertSame('Terima kasih.', $result);
    }

    public function test_preserves_paragraph_breaks_inside_the_reply(): void
    {
        $result = $this->parser()->parse('{"reply": "Terima kasih atas laporannya.\n\nBerikut penjelasan singkatnya."}');

        $this->assertSame("Terima kasih atas laporannya.\n\nBerikut penjelasan singkatnya.", $result);
    }

    public function test_throws_on_empty_reply(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parser()->parse('{"reply": "   "}');
    }

    public function test_throws_on_missing_reply_key(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parser()->parse('{"message": "no reply key here"}');
    }

    public function test_throws_on_non_json_response(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parser()->parse('Maaf, saya tidak bisa membuat balasan.');
    }
}
