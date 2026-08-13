<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RevealReporterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewReporterIdentity', $this->route('report'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan membuka data pelapor wajib diisi untuk keperluan audit.',
            'reason.min' => 'Alasan terlalu singkat, jelaskan dengan lebih rinci.',
        ];
    }
}
