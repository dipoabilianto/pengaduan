<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatTicket extends Model
{
    public const STATUS_MENUNGGU = 'menunggu_respon';

    public const STATUS_DITANGANI = 'sedang_ditangani';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_LABELS = [
        self::STATUS_MENUNGGU => 'Menunggu Respon',
        self::STATUS_DITANGANI => 'Sedang Ditangani',
        self::STATUS_SELESAI => 'Selesai',
    ];

    protected $fillable = [
        'phone_hash',
        'phone_enc',
        'name_enc',
        'channel_token',
        'related_report_id',
        'status',
        'ai_enabled',
        'assigned_to',
        'last_message_at',
        'last_message_preview',
        'last_citizen_message_at',
        'nudge_count',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'phone_enc' => 'encrypted',
            'name_enc' => 'encrypted',
            'ai_enabled' => 'boolean',
            'last_message_at' => 'datetime',
            'last_citizen_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * A phone number keeps its currently-active (non-selesai) ticket, but a closed
     * ticket is never reused for a new conversation — a fresh one is started instead,
     * with its own channel_token. Reuses ReportReporter's exact blind-index algorithm
     * (same HMAC key) so the two indexes never diverge.
     */
    public static function findOrStartFor(string $phone): self
    {
        $hash = ReportReporter::hashPhone($phone);

        $active = static::where('phone_hash', $hash)
            ->where('status', '!=', self::STATUS_SELESAI)
            ->latest('id')
            ->first();

        if ($active) {
            return $active;
        }

        $ticket = new static();
        // Set explicitly rather than relying on the DB column default — an
        // insert only sends the attributes actually assigned here, and the
        // in-memory model would otherwise read back null (not the DB's
        // true default) until a separate refresh().
        $ticket->phone_hash = $hash;
        $ticket->phone_enc = $phone;
        $ticket->channel_token = Str::random(64);
        $ticket->status = self::STATUS_MENUNGGU;
        $ticket->ai_enabled = true;
        $ticket->save();

        return $ticket;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(ChatRating::class)->orderByDesc('created_at');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function relatedReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'related_report_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    public function recordIncoming(string $preview): void
    {
        // Str::limit()'s $limit is the length BEFORE it appends "..." — a preview that
        // actually gets truncated ends up limit+3 chars long, 3 over this column's
        // VARCHAR(240) on a strict-mode MySQL (silently fine on SQLite, which doesn't
        // enforce column length — this broke only in production). Limit to 237 so the
        // truncated result (237 + "...") never exceeds 240.
        $this->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($preview, 237),
        ]);
    }

    /**
     * Deliberately separate from recordIncoming() (which every sender type touches) —
     * inactivity nudging/auto-close (ProcessInactiveChatTicketsCommand) must be measured
     * from the CITIZEN's own silence, not reset by an officer or AI reply.
     */
    public function recordCitizenActivity(): void
    {
        $this->update(['last_citizen_message_at' => now(), 'nudge_count' => 0]);
    }
}
