<?php

namespace App\Http\Requests;

use App\Models\Report;
use App\Rules\RecaptchaV2;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedCategories = $this->input('type') === 'whistleblowing'
            ? Report::CATEGORIES_WHISTLEBLOWING
            : Report::CATEGORIES_PENGADUAN;

        return [
            'type' => ['required', 'in:pengaduan,whistleblowing'],
            'category' => ['required', Rule::in($allowedCategories)],
            'reported_party' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:9', 'max:20'],
            'what' => ['required', 'string'],
            'who' => ['nullable', 'string'],
            'where' => ['nullable', 'string', 'max:255'],
            'when' => ['nullable', 'date'],
            'how' => ['nullable', 'string'],
            'why' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'g-recaptcha-response' => ['required', 'string', new RecaptchaV2],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis pelaporan wajib dipilih.',
            'category.required' => 'Kategori wajib diisi.',
            'category.in' => 'Kategori tidak sesuai dengan jenis pelaporan yang dipilih.',
            'phone.required' => 'Nomor HP/WhatsApp wajib diisi sebagai identitas pelaporan (PK).',
            'what.required' => 'Uraian kejadian (What) wajib diisi.',
            'g-recaptcha-response.required' => 'Verifikasi captcha wajib diisi.',
        ];
    }
}
