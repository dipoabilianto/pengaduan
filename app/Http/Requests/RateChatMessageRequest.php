<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RateChatMessageRequest extends FormRequest
{
    /**
     * Same ownership check as SendChatMessageRequest — the per-ticket session token
     * issued at ChatController::start(), matched against the ticket's channel_token.
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
            'scale' => ['required', 'integer', 'between:1,4'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
