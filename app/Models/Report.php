<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Report extends Model
{
    use HasFactory;

    /**
     * "Diteruskan ke Pejabat" merged into "Dalam Penanganan" — the only thing that status
     * ever captured was "pejabat sudah dipilih", which is now recorded by the same action
     * (pilih pejabat) that moves the report straight into penanganan, instead of a separate
     * no-op "Mulai Tangani" click. See ReportAdminService::assignToPejabat().
     */
    public const STATUS_LABELS = [
        'baru_masuk' => 'Baru Masuk',
        'terverifikasi_admin' => 'Terverifikasi Admin',
        'dalam_penanganan' => 'Dalam Penanganan',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak/Tidak Valid',
    ];

    /**
     * "tidak_valid" is a 5th urgency level for reports the AI judges as spam/frivolous/not
     * a real complaint — a recommendation Admin still reviews, same as every other flag.
     */
    public const URGENCY_LABELS = [
        'red_code' => 'Red Code',
        'tinggi' => 'Tinggi',
        'sedang' => 'Sedang',
        'rendah' => 'Rendah',
        'tidak_valid' => 'Tidak Valid',
    ];

    /**
     * Status internal (6 nilai, Bab 5.4 PDR) dikelompokkan jadi status publik
     * yang lebih sederhana — dipakai di cek status & daftar pengaduan publik.
     * Status internal admin TIDAK berubah, ini hanya lapisan tampilan.
     */
    public const PUBLIC_STATUS_GROUPS = [
        'diterima' => ['baru_masuk'],
        'diproses' => ['terverifikasi_admin'],
        'diteruskan' => ['dalam_penanganan'],
        'selesai' => ['selesai'],
        'ditolak' => ['ditolak'],
    ];

    public const PUBLIC_STATUS_LABELS = [
        'diterima' => 'Diterima',
        'diproses' => 'Diproses',
        'diteruskan' => 'Diteruskan ke Pejabat',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak/Tidak Valid',
    ];

    /**
     * State machine Bab 5.4 PDR — alur berurutan, tidak boleh lompat tahap.
     * Penilaian AI (Bab 5.3) berjalan otomatis di latar belakang begitu laporan
     * "Baru Masuk" tanpa mengubah status apa pun — Admin selalu jadi approver
     * final lewat "Terverifikasi Admin", baik AI sudah/belum selesai menilai.
     * Satu-satunya jalan keluar dari urutan normal adalah "ditolak", yang
     * sengaja tidak dicantumkan di sini karena bisa dituju dari status
     * manapun (ditangani khusus di canTransitionTo()); status sendiri
     * (no-op, mis. ganti catatan/urgensi tanpa ganti status) selalu diizinkan.
     */
    public const ALLOWED_TRANSITIONS = [
        'baru_masuk' => ['terverifikasi_admin'],
        'terverifikasi_admin' => ['dalam_penanganan'],
        'dalam_penanganan' => ['selesai'],
        'selesai' => [],
        'ditolak' => [],
    ];

    /**
     * Kategori keluhan layanan biasa (UU No. 25/2009 Pelayanan Publik).
     */
    public const CATEGORIES_PENGADUAN = [
        'Pelayanan Administrasi Kependudukan',
        'Keterlambatan Proses Layanan',
        'Perilaku/Sikap Petugas',
        'Sarana & Prasarana Pelayanan',
        'Lainnya',
    ];

    /**
     * Kategori dugaan pelanggaran, mengikuti pola WBS KPK/BPK/KemenPAN-RB
     * (KKN, gratifikasi, benturan kepentingan, dll) — draft awal, wajib
     * diverifikasi terhadap Permenpan-RB No. 5/2025 (lihat Bab 1.4 PDR).
     */
    public const CATEGORIES_WHISTLEBLOWING = [
        'Korupsi, Kolusi, Nepotisme (KKN)',
        'Dugaan Gratifikasi',
        'Pungutan Liar / Pemerasan',
        'Penggelapan dalam Jabatan',
        'Benturan Kepentingan',
        'Penyalahgunaan Wewenang/Jabatan',
        'Pelanggaran Kode Etik/Disiplin ASN',
        'Lainnya',
    ];

    protected $fillable = [
        'ticket_no',
        'category',
        'reported_party',
        'type',
        'what',
        'who',
        'where',
        'when',
        'how',
        'why',
        'status',
        'urgency_flag',
        'channel',
        'public_reply',
        'public_reply_by',
        'public_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'when' => 'datetime',
            'public_reply_at' => 'datetime',
        ];
    }

    public function reporter(): HasOne
    {
        return $this->hasOne(ReportReporter::class);
    }

    public function publicReplyAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'public_reply_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReportStatusLog::class)->latest('created_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ReportAssignment::class)->latest('assigned_at');
    }

    public function aiAssessment(): HasOne
    {
        return $this->hasOne(ReportAiAssessment::class)->latestOfMany();
    }

    /**
     * Bab 4.3 & 7 PDR: Red Code reports never appear in public-facing listings.
     */
    public function scopePubliclyVisible($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('urgency_flag')->orWhere('urgency_flag', '!=', 'red_code');
        });
    }

    /**
     * Dynamic per-role access: a role sees only the statuses it holds the matching
     * `laporan.status.*` permission for (no status permissions = sees nothing — deny by
     * default), and if it also holds `laporan.assigned-saja`, is further narrowed to
     * reports actually assigned to it. Superuser is exempt (Bab 2 & 5.6 PDR). Shared by
     * the admin reports list, dashboard, and Excel export so the rule can't drift.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('superuser')) {
            return $query;
        }

        $query->whereIn('status', self::visibleStatusesFor($user));

        if ($user->can('laporan.assigned-saja')) {
            $query->whereHas('assignments', fn ($q) => $q->where('assigned_to', $user->id));
        }

        return $query;
    }

    /**
     * Which internal statuses $user is allowed to see at all — used both by
     * scopeVisibleTo (to filter query results) and by the sidebar (to only list status
     * links the user actually has access to, rather than every status that exists).
     * Superuser bypasses the permission check entirely, same as scopeVisibleTo.
     *
     * @return array<int,string>
     */
    public static function visibleStatusesFor(User $user): array
    {
        if ($user->hasRole('superuser')) {
            return array_keys(self::STATUS_LABELS);
        }

        return collect(self::STATUS_LABELS)
            ->keys()
            ->filter(fn (string $status) => $user->can('laporan.status.'.$status))
            ->values()
            ->all();
    }

    /**
     * Count of $user's visible reports per internal status — shared by the dashboard
     * stat tiles and the sidebar's "Laporan" submenu so both always agree.
     *
     * @return array<string,int> keyed like STATUS_LABELS
     */
    public static function countsByStatus(User $user): array
    {
        $counts = static::query()
            ->visibleTo($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(self::STATUS_LABELS)
            ->keys()
            ->mapWithKeys(fn (string $status) => [$status => $counts->get($status, 0)])
            ->all();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function publicStatusGroup(): string
    {
        foreach (self::PUBLIC_STATUS_GROUPS as $group => $statuses) {
            if (in_array($this->status, $statuses, true)) {
                return $group;
            }
        }

        return 'diterima';
    }

    public function publicStatusLabel(): string
    {
        return self::PUBLIC_STATUS_LABELS[$this->publicStatusGroup()];
    }

    /**
     * A rejected report doesn't necessarily pass through every earlier stage first —
     * "Tidak Valid" (spam/frivolous) can send it straight from "Baru Masuk" to "Ditolak"
     * without ever being verified or forwarded. The public tracker must reflect what
     * genuinely happened, not assume the full sequence completed. Requires `statusLogs`
     * to be loaded (or it lazy-loads per report — see ReportService::findByTicketOrPhone).
     *
     * @return array<int,string> public group keys actually reached, in order (always includes "diterima")
     */
    public function reachedPublicGroups(): array
    {
        $everReached = $this->statusLogs->pluck('new_status')->push($this->status);

        $reached = ['diterima'];

        if ($everReached->contains('terverifikasi_admin')) {
            $reached[] = 'diproses';
        }

        // Historical status_logs for reports that reached this stage before the
        // "diteruskan_ke_pejabat"→"dalam_penanganan" merge still say the old status
        // string — check both so old timelines don't regress.
        if ($everReached->intersect(['diteruskan_ke_pejabat', 'dalam_penanganan'])->isNotEmpty()) {
            $reached[] = 'diteruskan';
        }

        return $reached;
    }

    /**
     * "ditolak" is reachable from any non-terminal status (rejection can
     * happen at any stage of review); staying on the current status is
     * always allowed (e.g. only changing urgency_flag or note).
     */
    public function canTransitionTo(string $newStatus): bool
    {
        if ($newStatus === $this->status) {
            return true;
        }

        if ($newStatus === 'ditolak') {
            return ! in_array($this->status, ['selesai', 'ditolak'], true);
        }

        return in_array($newStatus, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Whether applying $newStatus/$urgencyFlag/$note would actually change anything —
     * used to tell a genuine save from a no-op resubmit (e.g. "Simpan Perubahan" clicked
     * again with nothing edited), which should not be reported as a successful update.
     */
    public function wouldChangeWith(string $newStatus, ?string $urgencyFlag, ?string $note): bool
    {
        return ! ($this->status === $newStatus && $this->urgency_flag === $urgencyFlag && blank($note));
    }

    /**
     * "Selesai" and "Ditolak" are end states for the normal flow — Admin/Pejabat can no
     * longer move the report anywhere from here. Only Superuser may correct/reopen one.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, ['selesai', 'ditolak'], true);
    }

    /**
     * @param  bool  $unrestricted  bypasses the state machine entirely — only for a Superuser
     *                              correcting/reopening a locked ("Selesai"/"Ditolak") report.
     * @return array<int,string> status keys selectable from the current status (for building the admin dropdown)
     */
    public function validNextStatuses(bool $unrestricted = false): array
    {
        if ($unrestricted) {
            return array_keys(self::STATUS_LABELS);
        }

        return collect(self::STATUS_LABELS)
            ->keys()
            ->filter(fn (string $status) => $this->canTransitionTo($status))
            ->values()
            ->all();
    }
}
