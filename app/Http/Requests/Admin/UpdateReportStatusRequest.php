<?php

namespace App\Http\Requests\Admin;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'reply' => ['nullable', 'string', 'max:2000'],
            'pejabat_id' => [$this->routesToPejabatForTheFirstTime() ? 'required' : 'nullable', 'exists:users,id'],
        ];
    }

    /**
     * Moving from "Terverifikasi Admin" to "Dalam Penanganan" is the initial routing action
     * (status and pejabat assignment happen together in one click) — it must name a Pejabat
     * right here, otherwise the report would move forward with nobody actually assigned to
     * it. Re-selecting the same status once already routed (self-transition) is handled by
     * the separate "Alihkan ke Pejabat Lain" card instead, so it isn't required again here.
     */
    private function routesToPejabatForTheFirstTime(): bool
    {
        $report = $this->route('report');

        return $this->input('status') === 'dalam_penanganan' && $report->status === 'terverifikasi_admin';
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Report $report */
            $report = $this->route('report');

            // Superuser may correct/reopen a locked ("Selesai"/"Ditolak") report — everyone
            // else is bound by the normal sequential state machine.
            if ($this->filled('status') && ! $this->user()->hasRole('superuser') && ! $report->canTransitionTo($this->input('status'))) {
                $validator->errors()->add(
                    'status',
                    "Laporan berstatus \"{$report->statusLabel()}\" tidak dapat diubah langsung ke \"".(Report::STATUS_LABELS[$this->input('status')] ?? $this->input('status')).'". Ikuti urutan alur laporan.'
                );
            }

            // Urgensi "Tidak Valid" selalu berarti status "Ditolak" — jangan percaya UI saja,
            // request yang dipalsukan bisa saja mengirim kombinasi lain.
            if ($this->input('urgency_flag') === 'tidak_valid' && $this->input('status') !== 'ditolak') {
                $validator->errors()->add('status', 'Urgensi "Tidak Valid" hanya dapat disimpan dengan status "Ditolak".');
            }

            if ($this->filled('pejabat_id')) {
                $pejabat = User::find($this->input('pejabat_id'));

                if ($pejabat && ! $pejabat->can('laporan.terima-pelimpahan')) {
                    $validator->errors()->add('pejabat_id', 'Pengguna yang dipilih tidak punya izin menerima pelimpahan laporan.');
                }
            }

            // Client-side (Alpine) already disables the "Tandai Selesai" button until a reply
            // is written — this is the same rule enforced server-side, so a laporan can never
            // be marked Selesai without ever giving the pelapor an actual answer, even via a
            // forged/direct request.
            if ($this->input('status') === 'selesai' && ! $this->filled('reply') && blank($report->public_reply)) {
                $validator->errors()->add('reply', 'Laporan tidak dapat ditandai selesai tanpa balasan untuk pelapor.');
            }
        });
    }
}
