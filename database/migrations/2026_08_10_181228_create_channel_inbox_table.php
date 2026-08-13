<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_inbox', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['whatsapp', 'sosmed']);
            $table->string('external_ref')->nullable();
            $table->text('raw_message');
            $table->foreignId('linked_report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->enum('status', ['baru', 'ditriase', 'dikonversi', 'diabaikan'])->default('baru');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_inbox');
    }
};
