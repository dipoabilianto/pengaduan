<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only log, same shape as chat_messages — a ticket can close/reopen many
     * times (see ChatTicket::reopen()), so each closure's rating is its own row rather
     * than a single column that would overwrite prior IKM history.
     */
    public function up(): void
    {
        Schema::create('chat_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_ticket_id')->constrained('chat_tickets')->cascadeOnDelete();
            $table->unsignedTinyInteger('scale');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_ratings');
    }
};
