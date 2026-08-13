<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChannelInbox;
use App\Services\WhatsAppSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bab 6.2 & 7 PDR: menerima verifikasi handshake dan pesan masuk dari WhatsApp Cloud API.
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppSettingsService $settings): Response
    {
        $verifyToken = $settings->webhookVerifyToken();

        if ($request->query('hub_mode') === 'subscribe'
            && $verifyToken !== null
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request): JsonResponse
    {
        // Meta's payload nests messages per entry/change: entry[].changes[].value.messages[].
        // Each message is handled in its own try/catch so one malformed message doesn't
        // drop the rest of the batch.
        foreach ($request->input('entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                foreach (data_get($change, 'value.messages', []) as $message) {
                    try {
                        ChannelInbox::create([
                            'source' => 'whatsapp',
                            'external_ref' => $message['from'] ?? null,
                            'raw_message' => data_get($message, 'text.body', '[pesan non-teks]'),
                            'status' => 'baru',
                        ]);
                    } catch (Throwable $e) {
                        Log::warning('WhatsAppWebhookController: gagal menyimpan satu pesan masuk.', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
