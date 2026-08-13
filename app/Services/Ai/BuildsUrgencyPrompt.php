<?php

namespace App\Services\Ai;

use App\Models\Report;
use RuntimeException;

trait BuildsUrgencyPrompt
{
    use NormalizesAiText;

    /**
     * Mirrors the urgency table in PDR Bab 5.3 — draft criteria, pending legal
     * verification against Permenpan-RB No. 5/2025 per Bab 1.4.
     *
     * @param  string  $facts  Same Superuser-curated knowledge base as the chat assistant
     *                         (ChatFactsService) — e.g. jam layanan, kategori resmi, kebijakan
     *                         internal. Read as CONTEXT ONLY: it may inform the reasoning
     *                         (mis. laporan yang menyebut sesuatu di luar jam layanan resmi),
     *                         it must never override the fixed flag criteria below.
     */
    private function systemPrompt(string $facts = ''): string
    {
        $factsBlock = trim($facts) !== ''
            ? "\n\nREFERENSI TAMBAHAN (informasi resmi kantor ini, dikelola Superuser lewat halaman\n\"Pengetahuan Asisten ULP\") — boleh dipakai sebagai konteks kalau relevan dengan isi\nlaporan (mis. mencocokkan dengan jam layanan atau kategori resmi), TAPI TIDAK BOLEH\nmengubah/melonggarkan kriteria flag di atas:\n{$facts}"
            : '';

        return <<<PROMPT
            Anda adalah asisten triase untuk sistem pengaduan dan whistleblowing instansi pemerintah.
            Nilai tingkat urgensi laporan berikut berdasarkan kriteria berikut:
            - red_code: dugaan pidana, korupsi/gratifikasi, kekerasan, keterlibatan pejabat tinggi, risiko keselamatan.
            - tinggi: dampak signifikan pada layanan/masyarakat luas, berulang.
            - sedang: keluhan layanan standar, berdampak individual/kelompok kecil.
            - rendah: saran, masukan, keluhan ringan/administratif.
            - tidak_valid: HANYA untuk dua kasus spesifik ini — (1) isi benar-benar asal mengetik/acak/tidak
              bermakna (spam, karakter acak, atau jelas cuma uji coba sistem), atau (2) isi berupa pertanyaan
              atau hal yang sama sekali tidak sesuai dengan substansi layanan pengaduan instansi ini (di luar
              konteks layanan, bukan keluhan/saran/masukan apa pun). Ini tetap rekomendasi untuk ditinjau
              Admin, bukan keputusan final — kalau ragu antara tidak_valid dan rendah, pilih rendah.

            UTAMAKAN ISI/SUBSTANSI pengaduan, BUKAN kelengkapan data (What/Who/Where/When/How/Why). Laporan
            yang datanya minim TETAP HARUS dinilai berdasarkan isinya, BUKAN otomatis diarahkan ke tidak_valid
            atau skor rendah hanya karena kurang lengkap — laporan singkat/minim sekalipun bisa jadi cikal
            bakal laporan yang lebih lengkap di kemudian hari setelah ditindaklanjuti Admin. Selama isinya
            memang tentang keluhan, saran, masukan, atau mengindikasikan dampak pada individu/layanan, JANGAN
            diberi flag tidak_valid — nilai urgensinya dari kriteria red_code/tinggi/sedang/rendah di atas
            sesuai isi yang ada, walau ringkas. Kelengkapan data boleh disebut di reasoning sebagai catatan,
            tapi tidak boleh jadi alasan menurunkan flag ke tidak_valid.
            {$factsBlock}

            Balas HANYA dengan JSON valid tanpa teks lain, format persis:
            {"flag": "red_code|tinggi|sedang|rendah|tidak_valid", "score": 0-100, "reasoning": "alasan singkat"}
            PROMPT;
    }

    private function userPrompt(Report $report): string
    {
        return sprintf(
            "Jenis: %s\nKategori: %s\nWhat: %s\nWho: %s\nWhere: %s\nWhen: %s\nHow: %s\nWhy: %s",
            $report->type,
            $report->category,
            $report->what,
            $report->who ?? '-',
            $report->where ?? '-',
            $report->when?->toDateTimeString() ?? '-',
            $report->how ?? '-',
            $report->why ?? '-',
        );
    }

    /**
     * @return array{flag:string,score:int,reasoning:string}
     *
     * @throws RuntimeException if the model didn't return parseable, valid JSON.
     */
    private function parseAssessment(string $text): array
    {
        $cleaned = trim(preg_replace('/^```(json)?|```$/m', '', trim($text)));
        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded) || ! isset($decoded['flag'], $decoded['score'])) {
            throw new RuntimeException('AI response is not valid JSON: '.$text);
        }

        if (! in_array($decoded['flag'], ['red_code', 'tinggi', 'sedang', 'rendah', 'tidak_valid'], true)) {
            throw new RuntimeException('AI returned an unknown urgency flag: '.$decoded['flag']);
        }

        return [
            'flag' => $decoded['flag'],
            'score' => max(0, min(100, (int) $decoded['score'])),
            'reasoning' => $this->normalizeAiText((string) ($decoded['reasoning'] ?? '')),
        ];
    }
}
