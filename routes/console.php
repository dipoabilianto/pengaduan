<?php

use App\Jobs\AiHeartbeatJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notify:daily-summary')->dailyAt('07:00');

// Proves the queue worker + configured AI provider are genuinely alive, even during a
// lull in real citizen/admin activity — see AiHeartbeatJob and AiHealthService.
Schedule::job(new AiHeartbeatJob)->everyFiveMinutes();

// Chat inactivity nudge/auto-close policy — see ProcessInactiveChatTicketsCommand.
Schedule::command('chat:process-inactive-tickets')->everyFifteenMinutes();
