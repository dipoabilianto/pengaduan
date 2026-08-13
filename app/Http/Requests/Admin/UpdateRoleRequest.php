<?php

namespace App\Http\Requests\Admin;

use App\Models\Report;
use App\Support\ChatPermissions;
use App\Support\ReportPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('superuser');
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id),
                function ($attribute, $value, $fail) {
                    if (strtolower($value) === 'superuser') {
                        $fail('Nama "Superuser" sudah dipakai role sistem dan tidak boleh dipakai ulang.');
                    }
                },
            ],
            'abilities' => ['array'],
            'abilities.*' => [Rule::in([...array_keys(ReportPermissions::ABILITIES), ...array_keys(ChatPermissions::ABILITIES)])],
            'assigned_only' => ['nullable', 'boolean'],
            'statuses' => ['array'],
            'statuses.*' => [Rule::in(array_keys(Report::STATUS_LABELS))],
        ];
    }
}
