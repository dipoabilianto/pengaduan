<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kept physically separate from `reports` per PDR Bab 7: only Superuser-scoped
     * services may query this table; Admin/Pejabat never touch it directly.
     */
    public function up(): void
    {
        Schema::create('report_reporters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->text('name_enc')->nullable();
            $table->text('phone_enc');
            $table->string('phone_hash', 64)->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_reporters');
    }
};
