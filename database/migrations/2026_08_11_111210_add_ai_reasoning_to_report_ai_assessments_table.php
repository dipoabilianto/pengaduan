<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('report_ai_assessments', function (Blueprint $table) {
            $table->text('ai_reasoning')->nullable()->after('ai_raw_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_ai_assessments', function (Blueprint $table) {
            $table->dropColumn('ai_reasoning');
        });
    }
};
