<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\User;
use App\Services\Push\PushNotificationService;
use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bab 5.2/6 PDR: notifikasi ke Pejabat spesifik yang baru ditugaskan sebuah laporan.
 * Audiensnya sudah pasti ($pejabat) — tidak butuh NotificationAudienceService.
 */
class NotifyReportEscalatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly Report $report,
        public readonly User $pejabat,
    ) {
    }

    public function handle(PushNotificationService $push, WhatsAppNotificationService $whatsapp): void
    {
        $message = "Laporan {$this->report->ticket_no} diteruskan ke Anda.";

        try {
            $push->sendToUser($this->pejabat, 'Laporan Diteruskan', $message);
            $whatsapp->sendToPhone($this->pejabat->phone, $message);
        } catch (Throwable $e) {
            Log::warning('NotifyReportEscalatedJob: gagal mengirim notifikasi.', [
                'report_id' => $this->report->id,
                'pejabat_id' => $this->pejabat->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
