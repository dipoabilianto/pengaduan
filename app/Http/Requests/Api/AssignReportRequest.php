<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AssignReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('report'));
    }

    public function rules(): array
    {
        return [
            'pejabat_id' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
