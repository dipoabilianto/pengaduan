<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per real AI call attempt (urgency scoring, reply draft, chat draft polish,
     * chat auto-answer) — the actual evidence behind the "AI Otomatis" status badge,
     * instead of the badge only inferring health from queue backlog + a single cached
     * last-failure reason. See AiHealthService.
     */
    public function up(): void
    {
        Schema::create('ai_call_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('feature', ['urgency', 'reply', 'polish', 'chat', 'heartbeat']);
            $table->enum('outcome', ['success', 'failure']);
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_call_logs');
    }
};
