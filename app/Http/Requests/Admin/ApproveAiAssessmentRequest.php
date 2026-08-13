<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveAiAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approveAi', $this->route('report'));
    }

    public function rules(): array
    {
        return [
            'final_flag' => ['required', 'in:red_code,tinggi,sedang,rendah,tidak_valid'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reply' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $report = $this->route('report');

            // "Tidak Valid" routes straight to Ditolak instead of the normal Terverifikasi Admin step.
            $targetStatus = $this->input('final_flag') === 'tidak_valid' ? 'ditolak' : 'terverifikasi_admin';

            if (! $report->canTransitionTo($targetStatus)) {
                $validator->errors()->add('final_flag', 'Laporan sudah melewati tahap verifikasi urgensi dan tidak dapat disetujui ulang.');
            }
        });
    }
}
