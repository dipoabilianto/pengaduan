<?php

use App\Support\ReportPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * One-time restructure: report access moves from hardcoded role-name checks
     * ('admin'/'pejabat'/'superuser') to a dynamic permission system (see ReportPolicy,
     * Report::scopeVisibleTo). 'superuser' is untouched — it stays a fixed, protected
     * role. 'admin' and 'pejabat' are retired in favor of 'Administrator' and
     * 'Pelaksana', seeded here with a starting permission set that mirrors their old
     * hardcoded behavior, purely so existing accounts don't lose access overnight.
     * This is NOT the system default — a role created later through the Peran & Izin
     * UI starts with zero permissions (deliberately deny-by-default).
     */
    public function up(): void
    {
        foreach (ReportPermissions::all() as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $allStatuses = ReportPermissions::allStatusPermissions();

        $pengawas = Role::firstOrCreate(['name' => 'Pengawas', 'guard_name' => 'web']);
        $pengawas->syncPermissions($allStatuses);

        $administrator = Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
        $administrator->syncPermissions([
            ...$allStatuses,
            'laporan.ubah-status',
            'laporan.teruskan',
            'laporan.setujui-ai',
            'laporan.terima-pelimpahan',
        ]);

        // "diteruskan_ke_pejabat" merged into "dalam_penanganan" (see
        // 2026_08_13_120000_merge_diteruskan_ke_pejabat_into_dalam_penanganan) — this
        // migration re-runs against current code on every fresh test DB, so it must not
        // reference a status permission that no longer exists in ReportPermissions::all().
        $pelaksana = Role::firstOrCreate(['name' => 'Pelaksana', 'guard_name' => 'web']);
        $pelaksana->syncPermissions([
            ReportPermissions::statusPermission('dalam_penanganan'),
            ReportPermissions::ASSIGNED_ONLY,
            'laporan.ubah-status',
            'laporan.teruskan',
            'laporan.terima-pelimpahan',
        ]);

        Role::firstOrCreate(['name' => 'superuser', 'guard_name' => 'web']);

        $this->migrateUsers('admin', 'Administrator');
        $this->migrateUsers('pejabat', 'Pelaksana');
    }

    private function migrateUsers(string $oldRoleName, string $newRoleName): void
    {
        $oldRole = Role::where('name', $oldRoleName)->first();

        if (! $oldRole) {
            return;
        }

        foreach ($oldRole->users as $user) {
            $user->assignRole($newRoleName);
            $user->removeRole($oldRoleName);
        }

        $oldRole->delete();
    }

    public function down(): void
    {
        // Sengaja tidak reversible: setelah role diedit bebas lewat UI, tidak ada cara
        // aman merekonstruksi siapa dulunya 'admin' vs 'pejabat' dari keanggotaan
        // 'Administrator'/'Pelaksana' saat ini.
    }
};
