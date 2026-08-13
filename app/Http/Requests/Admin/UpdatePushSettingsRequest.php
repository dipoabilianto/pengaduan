<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePushSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('superuser');
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'string', 'max:100'],
            'service_account_json' => ['nullable', 'json'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_account_json.json' => 'File/isi service account harus berupa JSON yang valid.',
        ];
    }
}
