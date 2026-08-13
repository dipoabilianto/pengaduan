<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAiAssessment extends Model
{
    protected $fillable = [
        'report_id',
        'ai_score',
        'ai_suggested_flag',
        'ai_raw_response',
        'ai_reasoning',
        'reviewed_by',
        'approved_flag',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_raw_response' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
