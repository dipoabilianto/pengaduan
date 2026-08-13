<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\Notifications\NotificationAudienceService;
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
 * Bab 6 PDR: notifikasi prioritas tinggi saat laporan baru ditandai Red Code —
 * dispatch hanya sekali per transisi MASUK ke red_code (lihat ReportAdminService::updateStatus()).
 */
class NotifyRedCodeReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly Report $report)
    {
    }

    public function handle(NotificationAudienceService $audience, PushNotificationService $push, WhatsAppNotificationService $whatsapp): void
    {
        $message = "\u{1F534} RED CODE: Laporan {$this->report->ticket_no} — perlu tindakan segera.";

        foreach ($audience->usersVisibleToReport($this->report) as $user) {
            try {
                $push->sendToUser($user, 'Red Code — Tindakan Segera', $message, ['priority' => 'high']);
                $whatsapp->sendToPhone($user->phone, $message);
            } catch (Throwable $e) {
                Log::warning('NotifyRedCodeReportJob: gagal mengirim notifikasi ke satu user.', [
                    'report_id' => $this->report->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
