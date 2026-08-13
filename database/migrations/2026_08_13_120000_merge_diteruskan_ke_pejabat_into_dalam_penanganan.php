<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * "Diteruskan ke Pejabat" merges into "Dalam Penanganan" — the only thing that status
     * ever captured was "pejabat sudah dipilih", which duplicated across two clicks (pilih
     * pejabat, lalu klik "Mulai Tangani" yang tidak melakukan apa-apa selain memindahkan
     * status). Sekarang keduanya jadi satu aksi. See Report::STATUS_LABELS /
     * ReportAdminService::assignToPejabat().
     */
    private const OLD_STATUS = 'diteruskan_ke_pejabat';

    private const NEW_STATUS = 'dalam_penanganan';

    private const STATUS_VALUES = [
        'baru_masuk',
        'terverifikasi_admin',
        'dalam_penanganan',
        'selesai',
        'ditolak',
    ];

    public function up(): void
    {
        DB::table('reports')->where('status', self::OLD_STATUS)->update(['status' => self::NEW_STATUS]);

        Schema::table('reports', function (Blueprint $table) {
            $table->enum('status', self::STATUS_VALUES)->default('baru_masuk')->change();
        });

        // Historical log rows are free-text (no enum constraint) so they'd keep saying
        // "diteruskan_ke_pejabat" forever otherwise — rewrite them too so
        // Report::reachedPublicGroups() and the admin timeline read consistently.
        DB::table('report_status_logs')->where('old_status', self::OLD_STATUS)->update(['old_status' => self::NEW_STATUS]);
        DB::table('report_status_logs')->where('new_status', self::OLD_STATUS)->update(['new_status' => self::NEW_STATUS]);

        // A role that could already see "Diteruskan ke Pejabat" reports must not lose
        // visibility into what are now "Dalam Penanganan" reports just because it never
        // separately had that permission.
        $oldPermission = Permission::where('name', 'laporan.status.'.self::OLD_STATUS)->first();

        if ($oldPermission) {
            $newPermission = Permission::firstOrCreate(['name' => 'laporan.status.'.self::NEW_STATUS, 'guard_name' => 'web']);

            foreach ($oldPermission->roles as $role) {
                $role->givePermissionTo($newPermission);
            }

            $oldPermission->delete();
        }
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->enum('status', [...self::STATUS_VALUES, self::OLD_STATUS])->default('baru_masuk')->change();
        });

        Permission::firstOrCreate(['name' => 'laporan.status.'.self::OLD_STATUS, 'guard_name' => 'web']);
    }
};
