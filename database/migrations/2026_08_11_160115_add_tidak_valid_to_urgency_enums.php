<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Tidak Valid" — a 5th urgency level for reports the AI judges as spam/frivolous/not
     * a real complaint. Replaces the earlier standalone "is_spam" side-flag with a proper
     * urgency value that flows through the same badges/filters/approval logic as the rest.
     */
    private const URGENCY_VALUES = ['red_code', 'tinggi', 'sedang', 'rendah', 'tidak_valid'];

    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->enum('urgency_flag', self::URGENCY_VALUES)->nullable()->change();
        });

        Schema::table('report_ai_assessments', function (Blueprint $table) {
            $table->enum('ai_suggested_flag', self::URGENCY_VALUES)->nullable()->change();
            $table->enum('approved_flag', self::URGENCY_VALUES)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->enum('urgency_flag', ['red_code', 'tinggi', 'sedang', 'rendah'])->nullable()->change();
        });

        Schema::table('report_ai_assessments', function (Blueprint $table) {
            $table->enum('ai_suggested_flag', ['red_code', 'tinggi', 'sedang', 'rendah'])->nullable()->change();
            $table->enum('approved_flag', ['red_code', 'tinggi', 'sedang', 'rendah'])->nullable()->change();
        });
    }
};
