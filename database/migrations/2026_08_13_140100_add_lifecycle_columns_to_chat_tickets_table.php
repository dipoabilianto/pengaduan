<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * last_citizen_message_at is deliberately separate from the existing last_message_at
     * (which every sender type updates via ChatTicket::recordIncoming()) — inactivity
     * nudging/auto-close must be measured from the CITIZEN's own silence, not reset by
     * an officer or AI reply. nudge_count caps how many check-in nudges a ticket gets
     * before auto-closing. See ProcessInactiveChatTicketsCommand.
     */
    public function up(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table) {
            $table->timestamp('last_citizen_message_at')->nullable()->after('last_message_at');
            $table->unsignedTinyInteger('nudge_count')->default(0)->after('last_citizen_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table) {
            $table->dropColumn(['last_citizen_message_at', 'nudge_count']);
        });
    }
};
