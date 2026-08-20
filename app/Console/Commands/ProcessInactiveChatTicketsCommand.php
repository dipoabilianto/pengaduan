<?php

namespace App\Console\Commands;

use App\Jobs\Chat\ProcessInactiveChatTicketsJob;
use App\Services\ChatAdminService;
use Illuminate\Console\Command;

/**
 * Wrapper CLI manual untuk ProcessInactiveChatTicketsJob (mis. dijalankan lewat tinker
 * SSH/cPanel Terminal untuk memicu langsung tanpa menunggu jadwal). Penjadwalan asli
 * ada di routes/console.php lewat Schedule::job(), bukan lewat command ini.
 */
class ProcessInactiveChatTicketsCommand extends Command
{
    protected $signature = 'chat:process-inactive-tickets';

    protected $description = 'Kirim nudge AI ke pelapor yang tidak merespons, dan tutup otomatis tiket chat yang sudah lama diam';

    public function handle(ChatAdminService $chatAdmin): int
    {
        $result = (new ProcessInactiveChatTicketsJob)->handle($chatAdmin);

        $this->info("Nudge terkirim: {$result['nudged']}. Tiket ditutup otomatis: {$result['closed']}.");

        return self::SUCCESS;
    }
}
