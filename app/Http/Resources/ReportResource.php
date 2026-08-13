<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bab 6.1 PDR: dipakai aplikasi Android Admin/Pejabat. Sengaja TIDAK menyertakan relasi
 * "reporter" (nama/telepon pelapor terenkripsi) sama sekali — fase ini belum punya alur
 * "buka data pelapor + alasan" seperti Superuser di web (lihat ReportAdminService::
 * revealReporterIdentity()), jadi API tidak boleh membocorkannya sedikit pun.
 */
class ReportResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_no' => $this->ticket_no,
            'type' => $this->type,
            'category' => $this->category,
            'reported_party' => $this->reported_party,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'urgency_flag' => $this->urgency_flag,
            'channel' => $this->channel,
            'what' => $this->what,
            'who' => $this->who,
            'where' => $this->where,
            'when' => $this->when?->toIso8601String(),
            'how' => $this->how,
            'why' => $this->why,
            'public_reply' => $this->public_reply,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
