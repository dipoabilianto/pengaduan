<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Adds the 'laporan.kelola-inbox' ability for DBs that already ran the Fase 6/7
     * setup before this permission existed — RolesTableSeeder covers fresh installs,
     * this covers everyone else. Same pattern as 2026_08_12_020000_restructure_roles_and_permissions.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'laporan.kelola-inbox', 'guard_name' => 'web']);

        Role::where('name', 'Administrator')->first()?->givePermissionTo('laporan.kelola-inbox');
    }

    public function down(): void
    {
        Permission::where('name', 'laporan.kelola-inbox')->first()?->delete();
    }
};
