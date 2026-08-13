<?php

namespace App\Support;

/**
 * Permission registry for the Live Chat feature — sibling to ReportPermissions,
 * same role: single source of truth used by the data migration that creates these
 * permissions, the seeder that seeds default roles, and the Role management UI
 * checkboxes, so the three never drift apart.
 */
class ChatPermissions
{
    public const ABILITIES = [
        'chat.lihat' => 'Melihat kotak masuk & percakapan chat',
        'chat.balas' => 'Membalas & menangani tiket chat',
        'chat.tutup' => 'Menutup/membuka kembali tiket chat',
        'chat.lihat-nomor-telepon' => 'Melihat nomor HP lengkap pelanggan chat',
    ];

    public static function all(): array
    {
        return self::ABILITIES;
    }
}
