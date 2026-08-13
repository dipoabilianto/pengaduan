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
        Schema::table('reports', function (Blueprint $table) {
            $table->text('public_reply')->nullable();
            $table->foreignId('public_reply_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('public_reply_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('public_reply_by');
            $table->dropColumn(['public_reply', 'public_reply_at']);
        });
    }
};
