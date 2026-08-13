<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChannelInbox;
use App\Services\Instagram\InstagramClientFactory;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ChannelInboxController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ChannelInbox::class);

        $query = ChannelInbox::query()->latest();

        if ($request->query('status', 'baru') !== 'semua') {
            $query->where('status', $request->query('status', 'baru'));
        }

        return view('admin.inbox.index', [
            'items' => $query->paginate(20)->withQueryString(),
            'currentStatus' => $request->query('status', 'baru'),
        ]);
    }

    public function convert(Request $request, ChannelInbox $channelInbox, ReportService $reportService): RedirectResponse
    {
        $this->authorize('manage', $channelInbox);

        $report = $reportService->submit([
            'type' => 'pengaduan',
            'category' => 'Lainnya',
            'what' => $channelInbox->raw_message,
            'phone' => $channelInbox->external_ref ?? '-',
            'channel' => $channelInbox->source,
        ]);

        $channelInbox->update([
            'status' => 'dikonversi',
            'linked_report_id' => $report->id,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return back()->with('status', "Pesan dikonversi menjadi laporan {$report->ticket_no}.");
    }

    public function ignore(Request $request, ChannelInbox $channelInbox): RedirectResponse
    {
        $this->authorize('manage', $channelInbox);

        $channelInbox->update([
            'status' => 'diabaikan',
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return back()->with('status', 'Pesan ditandai diabaikan.');
    }

    /**
     * Balas keluar cuma didukung untuk Instagram (DM atau komentar, tergantung
     * external_type) — WhatsApp di Inbox Terpadu masih read-only seperti sebelumnya.
     * Beda dari notifikasi otomatis (WhatsAppNotificationService dkk): ini dipicu Admin
     * secara sinkron, jadi kegagalan HARUS tampil sebagai error, tidak boleh ditelan diam-diam.
     */
    public function reply(Request $request, ChannelInbox $channelInbox, InstagramClientFactory $instagram): RedirectResponse
    {
        $this->authorize('manage', $channelInbox);

        if ($channelInbox->source !== 'instagram') {
            abort(422, 'Balasan keluar belum didukung untuk sumber ini.');
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $client = $instagram->make();

        if (! $client) {
            return back()->withErrors(['message' => 'Instagram belum dikonfigurasi.']);
        }

        try {
            if ($channelInbox->external_type === 'comment') {
                $client->replyToComment($channelInbox->external_id, $data['message']);
            } else {
                $client->sendDirectMessage($channelInbox->external_ref, $data['message']);
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['message' => 'Gagal mengirim balasan: '.$e->getMessage()]);
        }

        $channelInbox->update([
            'status' => 'ditriase',
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return back()->with('status', 'Balasan terkirim.');
    }
}
