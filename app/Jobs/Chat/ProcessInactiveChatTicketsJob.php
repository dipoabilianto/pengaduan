<?php

namespace App\Jobs\Chat;

use App\Events\Chat\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatTicket;
use App\Services\ChatAdminService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Kebijakan siklus hidup percakapan chat (ditetapkan pemilik produk, 2026-08-13):
 * pelapor diam >1 jam -> AI menyapa/mengonfirmasi (maks 2x, di jam ke-1 & ke-3), diam
 * >=6 jam total -> tiket otomatis ditandai selesai. Dijadwalkan tiap 15 menit lewat
 * Schedule::job() (routes/console.php), BUKAN Schedule::command() — insiden 2026-08-20:
 * hosting shared sempat mematikan proc_open, yang dibutuhkan Schedule::command() untuk
 * fork subprocess, sehingga tugas ini gagal diam-diam selama berhari-hari (tiket chat
 * tidak pernah ditutup otomatis) tanpa ada alarm. Schedule::job() dispatch ke queue
 * database secara in-process, sama seperti AiHeartbeatJob — tidak butuh proc_open sama
 * sekali, jadi kambuh lagi tidak mungkin lewat jalur ini. Query di bawah idempoten
 * (aman dijalankan berkali-kali) karena tiap nudge terikat ke nudge_count spesifik,
 * bukan sekadar "masih dalam window".
 */
class ProcessInactiveChatTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const NUDGE_MESSAGES = [
        'Halo, masih di situ? Kalau masih ada yang mau ditanyakan seputar layanan Disdukcapil, silakan lanjutkan ya. Kalau sudah cukup, boleh juga kok diinfokan ke saya.',
        'Permisi, mau memastikan saja — apakah pertanyaannya sudah terjawab, atau masih ada yang perlu dibantu lagi?',
    ];

    /**
     * @return array{nudged: int, closed: int}
     */
    public function handle(ChatAdminService $chatAdmin): array
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

        return ['nudged' => $nudged, 'closed' => $closed->count()];
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
