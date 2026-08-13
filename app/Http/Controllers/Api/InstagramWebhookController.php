<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelInbox;
use App\Services\Instagram\InstagramClientFactory;
use App\Services\InstagramSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menerima verifikasi handshake dan pesan/komentar/mention masuk dari Instagram Graph API
 * (akun Instagram Professional yang terhubung ke Facebook Page) — kanal kedua Inbox Terpadu
 * di samping WhatsApp (WhatsAppWebhookController), pola sama persis.
 */
class InstagramWebhookController extends Controller
{
    public function verify(Request $request, InstagramSettingsService $settings): Response
    {
        $verifyToken = $settings->webhookVerifyToken();

        if ($request->query('hub_mode') === 'subscribe'
            && $verifyToken !== null
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, InstagramClientFactory $instagram): JsonResponse
    {
        foreach ($request->input('entry', []) as $entry) {
            foreach (data_get($entry, 'messaging', []) as $event) {
                $this->storeDirectMessage($event);
            }

            foreach (data_get($entry, 'changes', []) as $change) {
                $this->storeChange($change, $instagram);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * DM payload shape (Messenger-Platform style): entry[].messaging[].
     * "is_echo" pesan yang bisnis ini sendiri kirim (dipantulkan balik oleh Meta) — bukan
     * pesan masuk. Event tanpa key "message" (read receipt, reaction, dst) juga bukan pesan,
     * bukan payload rusak — dilewati diam-diam, bukan dicatat sebagai kegagalan.
     */
    private function storeDirectMessage(array $event): void
    {
        if (! isset($event['message']) || data_get($event, 'message.is_echo') === true) {
            return;
        }

        try {
            ChannelInbox::create([
                'source' => 'instagram',
                'external_type' => 'message',
                'external_ref' => data_get($event, 'sender.id'),
                'external_id' => data_get($event, 'message.mid'),
                'raw_message' => data_get($event, 'message.text', '[pesan non-teks]'),
                'status' => 'baru',
            ]);
        } catch (Throwable $e) {
            Log::warning('InstagramWebhookController: gagal menyimpan satu DM masuk.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Komentar payload sudah bawa teksnya langsung (value.text). Mention TIDAK bawa teks —
     * webhook cuma kasih value.comment_id, isinya harus di-fetch manual lewat Graph API
     * (fetchCommentText). Mention yang cuma bawa media_id (disebut di caption, bukan
     * komentar) di luar cakupan v1 ini — dilewati.
     */
    private function storeChange(array $change, InstagramClientFactory $instagram): void
    {
        $field = $change['field'] ?? null;

        try {
            if ($field === 'comments') {
                ChannelInbox::create([
                    'source' => 'instagram',
                    'external_type' => 'comment',
                    'external_ref' => data_get($change, 'value.from.username') ?? data_get($change, 'value.from.id'),
                    'external_id' => data_get($change, 'value.id'),
                    'raw_message' => data_get($change, 'value.text', '[komentar non-teks]'),
                    'status' => 'baru',
                ]);

                return;
            }

            if ($field === 'mentions') {
                $commentId = data_get($change, 'value.comment_id');

                if (! $commentId) {
                    return;
                }

                $text = $instagram->make()?->fetchCommentText($commentId);

                ChannelInbox::create([
                    'source' => 'instagram',
                    'external_type' => 'comment',
                    'external_ref' => null,
                    'external_id' => $commentId,
                    'raw_message' => $text ?? '[disebut di komentar — gagal mengambil isi, cek langsung di Instagram]',
                    'status' => 'baru',
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('InstagramWebhookController: gagal menyimpan satu komentar/mention masuk.', [
                'field' => $field,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
