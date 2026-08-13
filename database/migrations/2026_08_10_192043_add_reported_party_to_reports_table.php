<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Identitas Terlapor" — standard field in Indonesian WBS forms (KPK/BPK/KemenPAN-RB)
     * to name the accused party, distinct from the 5W1H narrative and never used
     * for pengaduan layanan (which doesn't accuse a specific individual).
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('reported_party')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('reported_party');
        });
    }
};
