<?php

namespace App\Http\Requests;

use App\Rules\RecaptchaV2;
use Illuminate\Foundation\Http\FormRequest;

class StartChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same shape as StoreReportRequest's phone rule — no regex, just length bounds,
     * matching the existing public-facing phone input convention. The recaptcha rule
     * blocks automated/bulk phone-number guessing (a phone number alone is otherwise
     * sufficient to reach a citizen's chat — see ChatController::start()'s docblock).
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:9', 'max:20'],
            'related_ticket_no' => ['nullable', 'string', 'max:50'],
            // Sent by the widget's "Lihat Riwayat Chat Sebelumnya" button to force full
            // history even inside the 6-12h hidden window — see ChatController::start().
            'reveal_history' => ['nullable', 'boolean'],
            'g-recaptcha-response' => ['required', 'string', new RecaptchaV2],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Nomor HP wajib diisi.',
            'g-recaptcha-response.required' => 'Verifikasi captcha wajib diisi.',
        ];
    }
}
