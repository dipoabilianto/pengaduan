<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ChatTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pusher\Pusher;

class ChatBroadcastAuthController extends Controller
{
    /**
     * Laravel's default /broadcasting/auth assumes Auth::user() — citizens aren't
     * logged-in Users, so they can't use it. This is the guest equivalent: the
     * widget proves it owns the ticket via a per-ticket session token (issued by
     * ChatController::start, matched with hash_equals against ChatTicket::channel_token),
     * then we hand-sign the Pusher-protocol channel auth response ourselves — the
     * exact same signature Reverb's own default auth route would produce, just
     * gated by our own guest-appropriate check instead of Auth::user().
     */
    public function authenticate(Request $request): Response
    {
        $request->validate([
            'socket_id' => ['required', 'string'],
            'channel_name' => ['required', 'string'],
        ]);

        $channelName = $request->string('channel_name')->toString();

        if (! preg_match('/^private-chat\.(\d+)$/', $channelName, $matches)) {
            abort(403, 'Channel tidak dikenali.');
        }

        $ticket = ChatTicket::find((int) $matches[1]);
        $token = $request->session()->get('chat_token_'.$matches[1]);

        if (! $ticket || ! $token || ! hash_equals($ticket->channel_token, $token)) {
            abort(403, 'Sesi chat tidak valid.');
        }

        $config = config('broadcasting.connections.reverb');

        $pusher = new Pusher($config['key'], $config['secret'], $config['app_id']);

        return response($pusher->authorizeChannel($channelName, $request->string('socket_id')->toString()), 200)
            ->header('Content-Type', 'application/json');
    }
}
