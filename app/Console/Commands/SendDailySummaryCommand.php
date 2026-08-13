<?php

namespace App\Console\Commands;

use App\Jobs\SendDailySummaryToUserJob;
use App\Models\Report;
use App\Models\User;
use Illuminate\Console\Command;

class SendDailySummaryCommand extends Command
{
    protected $signature = 'notify:daily-summary';

    protected $description = 'Kirim ringkasan status laporan harian ke setiap Admin/Pejabat/Pengawas (Bab 6.1 PDR)';

    public function handle(): int
    {
        $eligible = User::all()->filter(fn (User $user) => filled(Report::visibleStatusesFor($user)));

        foreach ($eligible as $user) {
            SendDailySummaryToUserJob::dispatch($user);
        }

        $this->info("Ringkasan harian dijadwalkan untuk {$eligible->count()} pengguna.");

        return self::SUCCESS;
    }
}
