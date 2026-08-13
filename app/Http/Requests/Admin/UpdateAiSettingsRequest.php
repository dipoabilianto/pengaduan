<?php

namespace App\Http\Requests\Admin;

use App\Services\AiSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('superuser');
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'in:'.implode(',', array_keys(AiSettingsService::PROVIDERS))],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
