<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendOfficerChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reply', $this->route('chatTicket'));
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'raw_draft' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
