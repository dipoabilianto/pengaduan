<?php

namespace Database\Seeders;

use App\Support\ChatPermissions;
use App\Support\ReportPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesTableSeeder extends Seeder
{
    /**
     * Seeds the same end state as the 2026_08_12_020000_restructure_roles_and_permissions
     * migration, for a fresh install (db:seed from scratch, no legacy 'admin'/'pejabat'
     * rows to migrate). Keep the two in sync if the default permission set changes.
     */
    public function run(): void
    {
        foreach (ReportPermissions::all() as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (ChatPermissions::all() as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $allStatuses = ReportPermissions::allStatusPermissions();

        Role::firstOrCreate(['name' => 'superuser', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'Pengawas', 'guard_name' => 'web'])
            ->syncPermissions($allStatuses);

        Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web'])
            ->syncPermissions([
                ...$allStatuses,
                'laporan.ubah-status',
                'laporan.teruskan',
                'laporan.setujui-ai',
                'laporan.terima-pelimpahan',
                'laporan.kelola-inbox',
                ...array_keys(ChatPermissions::ABILITIES),
            ]);

        Role::firstOrCreate(['name' => 'Pelaksana', 'guard_name' => 'web'])
            ->syncPermissions([
                ReportPermissions::statusPermission('dalam_penanganan'),
                ReportPermissions::ASSIGNED_ONLY,
                'laporan.ubah-status',
                'laporan.teruskan',
                'laporan.terima-pelimpahan',
            ]);
    }
}
