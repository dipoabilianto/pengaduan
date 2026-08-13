<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instagram jadi kanal kedua Inbox Terpadu (DM + komentar/mention), di samping WhatsApp
     * yang sudah ada. "external_id" (mis. comment_id) dan "external_type" (message/comment)
     * cuma relevan untuk Instagram — WhatsApp tetap null di keduanya, tidak butuh diisi
     * karena balas WhatsApp cukup ke nomor telepon (external_ref), tanpa target spesifik.
     */
    public function up(): void
    {
        Schema::table('channel_inbox', function (Blueprint $table) {
            $table->enum('source', ['whatsapp', 'sosmed', 'instagram'])->change();
            $table->string('external_id')->nullable()->after('external_ref');
            $table->enum('external_type', ['message', 'comment'])->nullable()->after('external_id');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->enum('channel', ['web', 'whatsapp', 'sosmed', 'instagram'])->default('web')->change();
        });
    }

    public function down(): void
    {
        Schema::table('channel_inbox', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'external_type']);
            $table->enum('source', ['whatsapp', 'sosmed'])->change();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->enum('channel', ['web', 'whatsapp', 'sosmed'])->default('web')->change();
        });
    }
};
