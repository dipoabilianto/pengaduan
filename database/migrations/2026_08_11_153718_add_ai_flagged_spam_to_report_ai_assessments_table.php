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
            $table->boolean('ai_flagged_spam')->default(false)->after('ai_reasoning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_ai_assessments', function (Blueprint $table) {
            $table->dropColumn('ai_flagged_spam');
        });
    }
};
