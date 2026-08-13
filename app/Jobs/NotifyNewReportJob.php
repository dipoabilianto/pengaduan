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
 * Bab 6 PDR: notifikasi laporan baru ke Admin/Pejabat yang berhak melihatnya. Sama
 * seperti ScoreReportUrgencyJob, ini best-effort — kegagalan kirim tidak boleh
 * berdampak apa pun ke laporan itu sendiri.
 */
class NotifyNewReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly Report $report)
    {
    }

    public function handle(NotificationAudienceService $audience, PushNotificationService $push, WhatsAppNotificationService $whatsapp): void
    {
        $message = "Laporan {$this->report->ticket_no} ({$this->report->category}) baru masuk.";

        foreach ($audience->usersVisibleToReport($this->report) as $user) {
            try {
                $push->sendToUser($user, 'Laporan Baru', $message);
                $whatsapp->sendToPhone($user->phone, $message);
            } catch (Throwable $e) {
                Log::warning('NotifyNewReportJob: gagal mengirim notifikasi ke satu user.', [
                    'report_id' => $this->report->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
