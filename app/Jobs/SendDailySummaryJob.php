<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dijadwalkan lewat Schedule::job() (routes/console.php), bukan Schedule::command() —
 * lihat catatan di ProcessInactiveChatTicketsJob soal insiden proc_open 2026-08-20.
 * Fan-out ke SendDailySummaryToUserJob per user tetap sama seperti sebelumnya.
 */
class SendDailySummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): int
    {
        $eligible = User::all()->filter(fn (User $user) => filled(Report::visibleStatusesFor($user)));

        foreach ($eligible as $user) {
            SendDailySummaryToUserJob::dispatch($user);
        }

        return $eligible->count();
    }
}
