<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same shape as StoreReportRequest's phone rule — no regex, just length bounds,
     * matching the existing public-facing phone input convention.
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:9', 'max:20'],
            'related_ticket_no' => ['nullable', 'string', 'max:50'],
            // Sent by the widget's "Lihat Riwayat Chat Sebelumnya" button to force full
            // history even inside the 6-12h hidden window — see ChatController::start().
            'reveal_history' => ['nullable', 'boolean'],
            'captcha' => ['required', 'string'],
        ];
    }

    /**
     * Same session-comparison pattern as StoreReportRequest — a phone number alone is
     * otherwise sufficient to reach a citizen's chat (see ChatController::start()'s
     * docblock), so this blocks automated/bulk guessing across many numbers.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $expected = session('captcha');

            if (! $expected || strtoupper(trim((string) $this->input('captcha'))) !== $expected) {
                $validator->errors()->add('captcha', 'Kode verifikasi tidak sesuai.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'captcha.required' => 'Kode verifikasi wajib diisi.',
        ];
    }
}
