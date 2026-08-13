<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per phone number, forever — `phone_hash` is UNIQUE (unlike
     * report_reporters', which is 1:many). A citizen re-contacting always resumes
     * the same thread instead of starting a new one. `channel_token` is a separate
     * random capability token (not derived from the phone) used only to authorize
     * the guest's own Reverb channel subscription — see ChatBroadcastAuthController.
     */
    public function up(): void
    {
        Schema::create('chat_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('phone_hash', 64)->unique();
            $table->text('phone_enc');
            $table->text('name_enc')->nullable();
            $table->string('channel_token', 64)->unique();
            $table->foreignId('related_report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->string('status')->default('menunggu_respon');
            $table->boolean('ai_enabled')->default(true);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 240)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_tickets');
    }
};
