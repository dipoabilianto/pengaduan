<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportReporter extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'report_id',
        'name_enc',
        'phone_enc',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $reporter) {
            if ($reporter->isDirty('phone_enc')) {
                $reporter->phone_hash = self::hashPhone($reporter->phone_enc);
            }
        });
    }

    /**
     * Blind index (HMAC keyed by APP_KEY) so a phone number can be looked up
     * by exact match without decrypting every row.
     */
    public static function hashPhone(string $phone): string
    {
        return hash_hmac('sha256', self::normalizePhone($phone), config('app.key'));
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }

    /**
     * Encrypted casts per PDR Bab 7: only Superuser-scoped services decrypt this data.
     */
    protected function casts(): array
    {
        return [
            'name_enc' => 'encrypted',
            'phone_enc' => 'encrypted',
            'created_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
