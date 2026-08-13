<?php

namespace App\Http\Requests\Admin;

use App\Services\AiSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerateReportReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('report'));
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! app(AiSettingsService::class)->isConfigured()) {
                $validator->errors()->add('reply', 'AI belum dikonfigurasi. Isi provider & API key di Pengaturan AI terlebih dahulu.');
            }
        });
    }
}
