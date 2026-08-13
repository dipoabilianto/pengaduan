<?php

namespace App\Services\Ai;

use RuntimeException;

trait BuildsPolishPrompt
{
    use NormalizesAiText;

    /**
     * Unlike BuildsReplyPrompt (which drafts a reply from scratch), this rewrites text
     * the officer already wrote — content/facts stay theirs, AI only smooths the tone.
     * Officer always reviews/edits the result before sending (human-in-the-loop, same
     * principle as the report reply draft flow).
     */
    private function polishSystemPrompt(): string
    {
        return <<<'PROMPT'
            Anda membantu petugas layanan pengaduan instansi pemerintah merapikan draf balasan
            kasar/singkat menjadi kalimat yang lebih sopan dan manusiawi, untuk dikirim ke
            pelapor lewat chat.

            Aturan:
            - Pertahankan SEMUA informasi/fakta/instruksi yang sudah ada di draf apa adanya —
              JANGAN menambah, menghapus, atau mengubah fakta apa pun (jam, alamat, syarat
              dokumen, dll) yang tidak disebutkan di draf.
            - Hanya perbaiki tata bahasa, kesopanan, dan kelengkapan kalimat — draf singkat/
              informal jadi kalimat lengkap yang sopan dan profesional.
            - TIDAK menyebut kata "AI", "algoritma", atau proses otomatis apa pun.
            - Tetap ringkas — jangan jadi bertele-tele, cukup 1-3 kalimat kecuali draf aslinya
              memang lebih panjang.
            - Bahasa Indonesia.

            Balas HANYA dengan JSON valid tanpa teks lain, format persis:
            {"text": "Hasil balasan yang sudah dirapikan."}
            PROMPT;
    }

    private function polishUserPrompt(string $draft): string
    {
        return "Draf petugas: {$draft}";
    }

    /**
     * @throws RuntimeException if the model didn't return parseable, valid JSON with text.
     */
    private function parsePolish(string $text): string
    {
        $cleaned = trim(preg_replace('/^```(json)?|```$/m', '', trim($text)));
        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded) || ! isset($decoded['text']) || ! is_string($decoded['text']) || trim($decoded['text']) === '') {
            throw new RuntimeException('AI polish response is not valid JSON: '.$text);
        }

        return $this->normalizeAiText($decoded['text']);
    }
}
