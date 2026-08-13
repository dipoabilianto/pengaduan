<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_ai_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->unsignedTinyInteger('ai_score')->nullable();
            $table->enum('ai_suggested_flag', ['red_code', 'tinggi', 'sedang', 'rendah'])->nullable();
            $table->json('ai_raw_response')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('approved_flag', ['red_code', 'tinggi', 'sedang', 'rendah'])->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_ai_assessments');
    }
};
