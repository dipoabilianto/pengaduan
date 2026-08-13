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
 * Bab 6.1 PDR: ringkasan status harian, dikirim per-user lewat job terpisah supaya satu
 * user yang lambat (mis. WhatsApp API lelet) tidak menahan pengiriman ke user lain.
 */
class SendDailySummaryToUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly User $user)
    {
    }

    public function handle(PushNotificationService $push, WhatsAppNotificationService $whatsapp): void
    {
        $counts = Report::countsByStatus($this->user);

        $message = sprintf(
            'Ringkasan harian: %d baru masuk, %d dalam penanganan.',
            $counts['baru_masuk'],
            $counts['dalam_penanganan'],
        );

        try {
            $push->sendToUser($this->user, 'Ringkasan Harian Laporan', $message);
            $whatsapp->sendToPhone($this->user->phone, $message);
        } catch (Throwable $e) {
            Log::warning('SendDailySummaryToUserJob: gagal mengirim ringkasan.', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
