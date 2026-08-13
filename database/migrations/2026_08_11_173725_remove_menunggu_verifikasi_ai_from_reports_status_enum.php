<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Menunggu Verifikasi Urgensi" merges into "Baru Masuk" — the transition between them
     * was always automatic (the AI scoring job), never a human decision, so it added a status
     * with no real meaning to Admin. AI still scores in the background exactly as before;
     * Admin remains the sole approver via "Terverifikasi Admin".
     */
    private const STATUS_VALUES = [
        'baru_masuk',
        'terverifikasi_admin',
        'diteruskan_ke_pejabat',
        'dalam_penanganan',
        'selesai',
        'ditolak',
    ];

    public function up(): void
    {
        DB::table('reports')->where('status', 'menunggu_verifikasi_ai')->update(['status' => 'baru_masuk']);

        Schema::table('reports', function (Blueprint $table) {
            $table->enum('status', self::STATUS_VALUES)->default('baru_masuk')->change();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->enum('status', [...self::STATUS_VALUES, 'menunggu_verifikasi_ai'])->default('baru_masuk')->change();
        });
    }
};
