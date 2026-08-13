<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_inbox', function (Blueprint $table) {
            $table->foreignId('handled_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable()->after('handled_by');
        });
    }

    public function down(): void
    {
        Schema::table('channel_inbox', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn('handled_at');
        });
    }
};
