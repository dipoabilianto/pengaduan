<?php

namespace App\Services;

class RedactionService
{
    private const PATTERNS = [
        // NIK: exactly 16 digits
        '/\b\d{16}\b/',
        // Indonesian phone numbers: 08xx / +628xx / 628xx
        '/\b(?:\+?62|0)8[1-9][0-9]{6,10}\b/',
        // Email addresses
        '/\b[\w.+-]+@[\w-]+\.[a-zA-Z]{2,}\b/',
    ];

    /**
     * Bab 4.3 & 7 PDR: best-effort detection of personal data (NIK, phone, email)
     * accidentally typed into free-text fields, masked before public display.
     * This is a heuristic safety net, not a substitute for not showing raw
     * free-text fields publicly in the first place.
     */
    public function redact(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return preg_replace(self::PATTERNS, '***', $text);
    }
}
