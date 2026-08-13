<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatRating extends Model
{
    public const SCALE_LABELS = [
        1 => 'Tidak Baik',
        2 => 'Kurang Baik',
        3 => 'Baik',
        4 => 'Sangat Baik',
    ];

    public const SCALE_EMOJI = [
        1 => '😞',
        2 => '🙁',
        3 => '🙂',
        4 => '😄',
    ];

    public $timestamps = false;

    protected $fillable = [
        'chat_ticket_id',
        'scale',
        'comment',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'scale' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'chat_ticket_id');
    }

    public function scaleLabel(): string
    {
        return self::SCALE_LABELS[$this->scale] ?? (string) $this->scale;
    }

    public function scaleEmoji(): string
    {
        return self::SCALE_EMOJI[$this->scale] ?? '';
    }
}
