<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Titik tunggal pengiriman WhatsApp keluar — mengunci filosofi ScoreReportUrgencyJob:
 * kegagalan kirim (belum dikonfigurasi, API error, nomor kosong) TIDAK PERNAH
 * melempar exception ke pemanggil, cukup dicatat log dan dilewati.
 */
class WhatsAppNotificationService
{
    public function __construct(private readonly WhatsAppClientFactory $factory)
    {
    }

    public function sendToPhone(?string $phone, string $message): void
    {
        if (blank($phone)) {
            return;
        }

        $client = $this->factory->make();

        if (! $client) {
            return;
        }

        try {
            $client->sendTextMessage($phone, $message);
        } catch (Throwable $e) {
            Log::warning('WhatsApp send failed.', [
                'phone' => substr($phone, 0, 4).'***',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
