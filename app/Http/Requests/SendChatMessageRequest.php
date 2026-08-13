<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    /**
     * Guests aren't Users — ownership is proven via the per-ticket session token
     * issued at ChatController::start(), matched with hash_equals against the
     * ticket's channel_token (same check ChatBroadcastAuthController uses).
     */
    public function authorize(): bool
    {
        $ticket = $this->route('chatTicket');
        $token = $this->session()->get('chat_token_'.$ticket->id);

        return $token && hash_equals($ticket->channel_token, $token);
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
