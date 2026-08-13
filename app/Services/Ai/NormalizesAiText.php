<?php

namespace App\Services\Ai;

trait NormalizesAiText
{
    /**
     * Models don't reliably self-limit whitespace — left unchecked, a reply can come
     * back with trailing spaces per line or runs of 3+ blank lines, which then render
     * as visibly stacked gaps in the chat bubble (`white-space: pre-line`) or an admin
     * textarea. Applied to every AI-authored text field before it's stored/shown, so
     * this holds regardless of which provider/model produced it.
     */
    private function normalizeAiText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Trailing spaces/tabs at the end of a line.
        $text = preg_replace('/[ \t]+(?=\n)/', '', $text);
        // Runs of spaces/tabs within a line, collapsed to one.
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);
        // More than one blank line between paragraphs, collapsed to exactly one.
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
