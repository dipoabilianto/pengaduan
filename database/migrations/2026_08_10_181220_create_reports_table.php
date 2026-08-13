<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->string('category');
            $table->enum('type', ['pengaduan', 'whistleblowing']);
            $table->text('what');
            $table->text('who')->nullable();
            $table->string('where')->nullable();
            $table->dateTime('when')->nullable();
            $table->text('how')->nullable();
            $table->text('why')->nullable();
            $table->enum('status', [
                'baru_masuk',
                'menunggu_verifikasi_ai',
                'terverifikasi_admin',
                'diteruskan_ke_pejabat',
                'dalam_penanganan',
                'selesai',
                'ditolak',
            ])->default('baru_masuk');
            $table->enum('urgency_flag', ['red_code', 'tinggi', 'sedang', 'rendah'])->nullable();
            $table->enum('channel', ['web', 'whatsapp', 'sosmed'])->default('web');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
