<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi bentuk data saja — aturan bisnis (state machine, urgensi "Tidak Valid" harus
 * "Ditolak", dsb) tetap ditegakkan satu tempat di ReportAdminService::updateStatus(),
 * bukan diduplikasi di sini (lihat Api\ReportController::updateStatus()).
 */
class UpdateReportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('report'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:baru_masuk,terverifikasi_admin,dalam_penanganan,selesai,ditolak'],
            'urgency_flag' => ['nullable', 'in:red_code,tinggi,sedang,rendah,tidak_valid'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
