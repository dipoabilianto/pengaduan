<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Setting;
use App\Models\User;

/**
 * Superuser-editable knowledge the chat AI is allowed to answer from (office hours,
 * document requirements per service, etc.) — deliberately NOT hardcoded in the AI
 * prompt, since only the office itself can vouch for what's actually correct. The AI
 * is instructed (BuildsChatAnswerPrompt) to escalate to staff for anything outside
 * whatever is saved here, rather than guess.
 */
class ChatFactsService
{
    private const KEY = 'chat_ai_facts';

    public function get(): string
    {
        return Setting::get(self::KEY) ?: $this->defaultFacts();
    }

    public function save(string $facts, ?int $updatedBy = null): void
    {
        Setting::put(self::KEY, $facts, $updatedBy);
    }

    /**
     * Pre-filled the first time the settings page is opened, and used as a fallback
     * if the setting is ever cleared out entirely — general info only, no document
     * requirements guessed on the office's behalf.
     */
    public function defaultFacts(): string
    {
        $pengaduan = implode(', ', Report::CATEGORIES_PENGADUAN);
        $whistleblowing = implode(', ', Report::CATEGORIES_WHISTLEBLOWING);

        return <<<TEXT
            Jam layanan: Senin-Jumat, 08.00-16.00 WIB. Tutup Sabtu/Minggu/hari libur nasional.

            Cara cek status laporan: buka halaman "Cek Status" di situs ini, masukkan nomor tiket atau nomor HP yang dipakai saat lapor.

            Kategori pengaduan pelayanan publik yang bisa dilaporkan: {$pengaduan}.

            Kategori whistleblowing/dugaan pelanggaran yang bisa dilaporkan: {$whistleblowing}.

            Untuk membuat laporan baru: tombol "Buat Pengaduan" di halaman utama situs ini.
            TEXT;
    }
}
