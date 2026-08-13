<?php

namespace App\Http\Requests\Admin;

use App\Models\ChatTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChatStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->route('chatTicket'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(ChatTicket::STATUS_LABELS))],
        ];
    }
}
