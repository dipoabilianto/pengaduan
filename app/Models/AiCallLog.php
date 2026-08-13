<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCallLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['feature', 'outcome', 'reason', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public const FEATURE_LABELS = [
        'urgency' => 'Penilaian Urgensi',
        'reply' => 'Draf Balasan Laporan',
        'polish' => 'Rapikan Draf Chat',
        'chat' => 'Jawab Chat Otomatis',
        'heartbeat' => 'Cek Rutin (Heartbeat)',
    ];
}
