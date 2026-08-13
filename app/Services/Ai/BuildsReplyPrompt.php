<?php

namespace App\Services\Ai;

use App\Models\Report;
use RuntimeException;

trait BuildsReplyPrompt
{
    use NormalizesAiText;

    /**
     * Asks explicitly for varied phrasing each call — the whole point is that Admin can
     * regenerate and get something that doesn't read like the same canned template every time.
     */
    private function replySystemPrompt(): string
    {
        return <<<'PROMPT'
            Anda adalah admin layanan pengaduan instansi pemerintah yang membalas pelapor bahwa
            laporannya tidak dapat ditindaklanjuti karena dinilai tidak valid (iseng/spam/kosong/
            tidak lengkap).

            Tulis SATU balasan singkat dalam Bahasa Indonesia yang:
            - Sopan, profesional, dan tetap menghargai pelapor — bukan bernada menuduh atau kasar.
            - TIDAK menyebut kata "AI", "algoritma", "sistem otomatis", atau proses penilaian internal.
            - TIDAK menyalin mentah alasan teknis penolakan — sampaikan secara umum saja (misalnya:
              informasi kurang lengkap atau tidak sesuai kategori pengaduan).
            - Mempersilakan pelapor mengajukan laporan baru dengan kronologi yang lebih jelas.
            - WAJIB memvariasikan pilihan kata, urutan kalimat, dan gaya bahasa setiap kali diminta —
              jangan pernah mengulang susunan kalimat yang sama seperti template baku, supaya terasa
              ditulis oleh manusia, bukan pesan otomatis yang berulang.
            - WAJIB dipecah jadi 2 paragraf pendek dipisah SATU baris kosong (karakter newline ganda,
              \n\n): paragraf pertama cuma kalimat pembuka/ucapan terima kasih (1 kalimat singkat),
              paragraf kedua berisi penjelasan singkat dan ajakan mengajukan laporan baru (1-3 kalimat).
              Jangan gabungkan semuanya jadi satu paragraf panjang.

            Balas HANYA dengan JSON valid tanpa teks lain, format persis (perhatikan \n\n di dalam value):
            {"reply": "Kalimat pembuka singkat.\n\nParagraf isi di sini."}
            PROMPT;
    }

    private function replyUserPrompt(Report $report, ?string $reasoning): string
    {
        return sprintf(
            "Jenis: %s\nKategori: %s\nIsi laporan (What): %s\nAlasan internal penolakan (jangan disalin mentah, cuma konteks): %s",
            $report->type,
            $report->category,
            $report->what,
            $reasoning ?: '-',
        );
    }

    /**
     * @throws RuntimeException if the model didn't return parseable, valid JSON with a reply.
     */
    private function parseReply(string $text): string
    {
        $cleaned = trim(preg_replace('/^```(json)?|```$/m', '', trim($text)));
        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded) || ! isset($decoded['reply']) || ! is_string($decoded['reply']) || trim($decoded['reply']) === '') {
            throw new RuntimeException('AI reply response is not valid JSON: '.$text);
        }

        return $this->normalizeAiText($decoded['reply']);
    }
}
