<?php

namespace App\Console\Commands;

use App\Events\Chat\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatTicket;
use App\Services\ChatAdminService;
use Illuminate\Console\Command;

/**
 * Kebijakan siklus hidup percakapan chat (ditetapkan pemilik produk, 2026-08-13):
 * pelapor diam >1 jam -> AI menyapa/mengonfirmasi (maks 2x, di jam ke-1 & ke-3), diam
 * >=6 jam total -> tiket otomatis ditandai selesai. Dijadwalkan tiap 15 menit
 * (routes/console.php) — query di bawah idempoten (aman dijalankan berkali-kali)
 * karena tiap nudge terikat ke nudge_count spesifik, bukan sekadar "masih dalam window".
 */
class ProcessInactiveChatTicketsCommand extends Command
{
    protected $signature = 'chat:process-inactive-tickets';

    protected $description = 'Kirim nudge AI ke pelapor yang tidak merespons, dan tutup otomatis tiket chat yang sudah lama diam';

    private const NUDGE_MESSAGES = [
        'Halo, masih di situ? Kalau masih ada yang mau ditanyakan seputar layanan Disdukcapil, silakan lanjutkan ya. Kalau sudah cukup, boleh juga kok diinfokan ke saya.',
        'Permisi, mau memastikan saja — apakah pertanyaannya sudah terjawab, atau masih ada yang perlu dibantu lagi?',
    ];

    public function handle(ChatAdminService $chatAdmin): int
    {
        $now = now();
        $nudged = 0;

        foreach ([0 => 1, 1 => 3] as $fromNudgeCount => $hoursIdle) {
            $candidates = ChatTicket::where('status', '!=', ChatTicket::STATUS_SELESAI)
                ->where('nudge_count', $fromNudgeCount)
                ->whereNotNull('last_citizen_message_at')
                ->where('last_citizen_message_at', '<=', $now->copy()->subHours($hoursIdle))
                ->get();

            foreach ($candidates as $ticket) {
                $this->sendNudge($ticket, self::NUDGE_MESSAGES[$fromNudgeCount]);
                $nudged++;
            }
        }

        $closed = ChatTicket::where('status', '!=', ChatTicket::STATUS_SELESAI)
            ->whereNotNull('last_citizen_message_at')
            ->where('last_citizen_message_at', '<=', $now->copy()->subHours(6))
            ->get();

        foreach ($closed as $ticket) {
            $chatAdmin->updateStatus($ticket, ChatTicket::STATUS_SELESAI);
        }

        $this->info("Nudge terkirim: {$nudged}. Tiket ditutup otomatis: {$closed->count()}.");

        return self::SUCCESS;
    }

    private function sendNudge(ChatTicket $ticket, string $body): void
    {
        $message = ChatMessage::create([
            'chat_ticket_id' => $ticket->id,
            'sender_type' => ChatMessage::SENDER_AI,
            'body' => $body,
            'ai_generated' => true,
            'created_at' => now(),
        ]);

        $ticket->recordIncoming($body);
        $ticket->increment('nudge_count');

        broadcast(new ChatMessageSent($message));
    }
}
