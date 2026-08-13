<?php

namespace App\Http\Requests\Admin;

use App\Services\AiSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PolishChatDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reply', $this->route('chatTicket'));
    }

    public function rules(): array
    {
        return [
            'draft' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! app(AiSettingsService::class)->isConfigured()) {
                $validator->errors()->add('draft', 'AI belum dikonfigurasi. Isi provider & API key di Pengaturan AI terlebih dahulu.');
            }
        });
    }
}
