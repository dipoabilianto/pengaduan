<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A phone number is no longer bound to exactly one ChatTicket forever — a citizen
     * can open a new ticket once their previous one is closed. See ChatTicket::findOrStartFor().
     */
    public function up(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table) {
            $table->dropUnique(['phone_hash']);
            $table->index('phone_hash');
        });
    }

    public function down(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table) {
            $table->dropIndex(['phone_hash']);
            $table->unique('phone_hash');
        });
    }
};
