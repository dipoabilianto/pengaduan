<?php

use App\Jobs\AiHeartbeatJob;
use App\Jobs\Chat\ProcessInactiveChatTicketsJob;
use App\Jobs\SendDailySummaryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PENTING: semua entri di file ini SENGAJA memakai Schedule::job(), bukan
// Schedule::command(). Schedule::command() memerlukan proc_open untuk fork subprocess;
// shared hosting di sini sempat mematikan proc_open (disable_functions), yang membuat
// chat:process-inactive-tickets gagal diam-diam selama berhari-hari (insiden
// 2026-08-20 — tiket chat tidak pernah ditutup otomatis, tanpa alarm apa pun karena
// error hanya masuk laravel.log, bukan output cron). Schedule::job() dispatch ke queue
// database secara in-process, tidak butuh proc_open sama sekali. Kalau menambah jadwal
// baru di sini, pakai Schedule::job(new SomeJob) — JANGAN Schedule::command().

Schedule::job(new SendDailySummaryJob)->dailyAt('07:00');

// Proves the queue worker + configured AI provider are genuinely alive, even during a
// lull in real citizen/admin activity — see AiHeartbeatJob and AiHealthService.
Schedule::job(new AiHeartbeatJob)->everyFiveMinutes();

// Chat inactivity nudge/auto-close policy — see ProcessInactiveChatTicketsJob.
Schedule::job(new ProcessInactiveChatTicketsJob)->everyFifteenMinutes();
