<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelInbox extends Model
{
    protected $table = 'channel_inbox';

    protected $fillable = [
        'source',
        'external_ref',
        'external_id',
        'external_type',
        'raw_message',
        'linked_report_id',
        'status',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function linkedReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'linked_report_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'baru');
    }
}
