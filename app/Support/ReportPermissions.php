<?php

namespace App\Support;

use App\Models\Report;

/**
 * Single source of truth for the permission names driving the dynamic role/access
 * system (see ReportPolicy, Report::scopeVisibleTo) — used by the data migration that
 * creates them, the seeder that seeds default roles, and the Role management UI that
 * renders their checkboxes, so the three never drift apart.
 */
class ReportPermissions
{
    /**
     * General abilities — human label shown as a checkbox in the Role form.
     *
     * @var array<string,string>
     */
    public const ABILITIES = [
        'laporan.ubah-status' => 'Mengubah status/urgensi laporan',
        'laporan.teruskan' => 'Meneruskan/melimpahkan laporan ke pengguna lain',
        'laporan.setujui-ai' => 'Menyetujui rekomendasi AI',
        'laporan.terima-pelimpahan' => 'Bisa dipilih sebagai tujuan pelimpahan laporan',
        'laporan.kelola-inbox' => 'Mengelola Inbox Terpadu (WhatsApp & Instagram)',
    ];

    /**
     * Toggle: narrows visibility to only reports assigned to the user, independent of
     * which statuses they're allowed to see.
     */
    public const ASSIGNED_ONLY = 'laporan.assigned-saja';

    public static function statusPermission(string $status): string
    {
        return 'laporan.status.'.$status;
    }

    /**
     * @return array<int,string> every status permission name, e.g. "laporan.status.selesai"
     */
    public static function allStatusPermissions(): array
    {
        return array_map(
            fn (string $status) => self::statusPermission($status),
            array_keys(Report::STATUS_LABELS)
        );
    }

    /**
     * Every permission this feature defines, name => human label — what gets created by
     * the migration/seeder and rendered as checkboxes in the Role form.
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        $statusPermissions = collect(Report::STATUS_LABELS)
            ->mapWithKeys(fn (string $label, string $status) => [self::statusPermission($status) => "Lihat status: {$label}"]);

        return [
            ...self::ABILITIES,
            self::ASSIGNED_ONLY => 'Hanya laporan yang di-assign ke diri sendiri',
            ...$statusPermissions->all(),
        ];
    }
}
