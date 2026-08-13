<?php

namespace App\Services;

/**
 * Jaring pengaman deterministik untuk kata kasar/vulgar & indikasi pelecehan di chat —
 * BUKAN pengganti penilaian AI (yang menilai nuansa/konteks saat AI aktif), tapi
 * pengecekan instan/gratis yang tetap jalan walau AI mati/belum dikonfigurasi. Daftar
 * di bawah bukan daftar lengkap — cukup untuk menangkap kasus paling umum, mudah
 * ditambah nanti tanpa mengubah cara kerja service ini.
 */
class ContentModerationService
{
    private const PROFANITY = [
        'anjing', 'anjir', 'goblok', 'tolol', 'bangsat', 'kontol', 'memek', 'ngentot',
        'jancok', 'jancuk', 'asu', 'bego', 'idiot', 'bajingan', 'brengsek', 'sialan',
        'kampret', 'keparat', 'pantek', 'pukimak', 'lonte', 'pelacur', 'sundal',
        'perek', 'pepek', 'kimak', 'tai', 'taik',
    ];

    /**
     * Dicek sebagai FRASA (bukan kata tunggal) supaya lebih presisi dan tidak banyak
     * salah tangkap (false positive) dibanding daftar kata lepas.
     */
    private const HARASSMENT_PHRASES = [
        'mau ngentot', 'ajak ketemu', 'foto bugil', 'kirim foto bugil', 'video call bugil',
        'buka baju', 'body kamu', 'ukuran toket', 'ukuran memek', 'ukuran kontol',
        'bunuh kamu', 'akan saya bunuh', 'saya perkosa', 'akan saya perkosa', 'ingin memperkosa',
    ];

    /**
     * @return array{flagged: bool, reason: ?string}
     */
    public function check(string $text): array
    {
        $lower = mb_strtolower($text);
        $noSpaces = preg_replace('/\s+/u', '', $lower);

        foreach (self::PROFANITY as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $lower)) {
                return ['flagged' => true, 'reason' => 'Terdeteksi kata kasar/vulgar.'];
            }

            // Menangkap spasi sisipan sederhana ("a n j i n g") — hanya untuk kata
            // cukup panjang supaya tidak salah tangkap kata pendek di dalam kata lain.
            if (mb_strlen($word) >= 4 && str_contains($noSpaces, $word)) {
                return ['flagged' => true, 'reason' => 'Terdeteksi kata kasar/vulgar.'];
            }
        }

        foreach (self::HARASSMENT_PHRASES as $phrase) {
            if (str_contains($lower, $phrase)) {
                return ['flagged' => true, 'reason' => 'Terdeteksi indikasi pelecehan/ancaman.'];
            }
        }

        return ['flagged' => false, 'reason' => null];
    }
}
