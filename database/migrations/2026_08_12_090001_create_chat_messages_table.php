<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only log — no updated_at, same shape as report_status_logs. `raw_draft`
     * keeps the officer's pre-polish text when "Haluskan dengan AI" was used (Fase 3),
     * purely for internal transparency, never shown to the citizen. `escalation_flag`
     * marks a citizen message AI declined/failed to answer (Fase 4).
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_ticket_id')->constrained('chat_tickets')->cascadeOnDelete();
            $table->string('sender_type');
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->text('raw_draft')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->boolean('escalation_flag')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
