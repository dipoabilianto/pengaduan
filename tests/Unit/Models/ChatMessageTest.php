<?php

namespace Tests\Unit\Models;

use App\Models\ChatMessage;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    public function test_formatted_body_converts_bold_markdown_to_strong(): void
    {
        $message = new ChatMessage(['body' => 'Halo, saya **Tata**, ada yang bisa dibantu?']);

        $this->assertSame('Halo, saya <strong>Tata</strong>, ada yang bisa dibantu?', $message->formattedBody());
    }

    public function test_formatted_body_escapes_html_before_applying_markdown(): void
    {
        $message = new ChatMessage(['body' => '<script>alert(1)</script> **Tata**']);

        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt; <strong>Tata</strong>',
            $message->formattedBody()
        );
    }

    public function test_formatted_body_leaves_plain_text_unchanged(): void
    {
        $message = new ChatMessage(['body' => 'Terima kasih infonya.']);

        $this->assertSame('Terima kasih infonya.', $message->formattedBody());
    }
}
