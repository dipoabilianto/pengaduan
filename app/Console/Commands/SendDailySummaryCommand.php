<?php

namespace App\Console\Commands;

use App\Jobs\SendDailySummaryJob;
use Illuminate\Console\Command;

/**
 * Wrapper CLI manual untuk SendDailySummaryJob. Penjadwalan asli ada di
 * routes/console.php lewat Schedule::job(), bukan lewat command ini.
 */
class SendDailySummaryCommand extends Command
{
    protected $signature = 'notify:daily-summary';

    protected $description = 'Kirim ringkasan status laporan harian ke setiap Admin/Pejabat/Pengawas (Bab 6.1 PDR)';

    public function handle(): int
    {
        $count = (new SendDailySummaryJob)->handle();

        $this->info("Ringkasan harian dijadwalkan untuk {$count} pengguna.");

        return self::SUCCESS;
    }
}
