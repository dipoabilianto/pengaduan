<?php

use App\Support\ChatPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Adds the Live Chat permissions for DBs that already ran the role-restructure
     * migration before these existed — RolesTableSeeder covers fresh installs, this
     * covers everyone else. Same pattern as 2026_08_12_020000_restructure_roles_and_permissions.
     * Administrator gets all four by default (mirrors its existing broad laporan.*
     * access); Pengawas/Pelaksana get none — Superuser assigns chat access explicitly
     * per role via the Peran & Izin UI, same deny-by-default posture as any new role.
     */
    public function up(): void
    {
        foreach (ChatPermissions::all() as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'Administrator')->first()?->givePermissionTo(array_keys(ChatPermissions::ABILITIES));
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(ChatPermissions::ABILITIES))->delete();
    }
};
