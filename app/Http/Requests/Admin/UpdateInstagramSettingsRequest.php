<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstagramSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('superuser');
    }

    public function rules(): array
    {
        return [
            'business_account_id' => ['required', 'string', 'max:100'],
            'access_token' => ['nullable', 'string', 'max:500'],
            'webhook_verify_token' => ['required', 'string', 'max:200'],
        ];
    }
}
