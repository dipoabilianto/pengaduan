<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $pejabat = User::find($this->input('pejabat_id'));

            if ($pejabat && ! $pejabat->can('laporan.terima-pelimpahan')) {
                $validator->errors()->add('pejabat_id', 'Pengguna yang dipilih tidak punya izin menerima pelimpahan laporan.');
            }

            $report = $this->route('report');
            $alreadyRouted = $report->status === 'dalam_penanganan';

            if (! $alreadyRouted && ! $report->canTransitionTo('dalam_penanganan')) {
                $validator->errors()->add('pejabat_id', "Laporan berstatus \"{$report->statusLabel()}\" belum bisa diteruskan ke pejabat. Verifikasi urgensi terlebih dahulu.");
            }
        });
    }
}
