<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\RedactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly RedactionService $redaction)
    {
    }

    /**
     * Bab 4.4 & 8.2 PDR: landing page + public statistics + public report list
     * combined into a single page. Red Code reports are excluded throughout
     * (Report::publiclyVisible) and free-text fields are auto-redacted.
     */
    public function __invoke(Request $request): View
    {
        $visible = Report::publiclyVisible();

        $reports = (clone $visible)
            ->when(
                $request->filled('status') && isset(Report::PUBLIC_STATUS_GROUPS[$request->input('status')]),
                fn ($q) => $q->whereIn('status', Report::PUBLIC_STATUS_GROUPS[$request->input('status')])
            )
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->latest()
            ->paginate(10, ['*'], 'page')
            ->withQueryString()
            ->through(fn (Report $report) => [
                'ticket_no' => $report->ticket_no,
                'type' => $report->type,
                'category' => $report->category,
                'where' => $this->redaction->redact($report->where),
                // Bab 4.3 PDR mewajibkan auto-redaction pada teks bebas sebelum tampil
                // publik — berlaku juga di sini, bukan cuma "where", karena "what" &
                // balasan sama-sama teks bebas yang berisiko memuat data pribadi.
                // Dipotong di daftar (bukan detail penuh) supaya tetap ringkas.
                'what' => Str::limit($this->redaction->redact($report->what), 200),
                'reply' => $report->public_reply ? $this->redaction->redact($report->public_reply) : null,
                'reply_at' => $report->public_reply_at,
                'status' => $report->publicStatusLabel(),
                'created_at' => $report->created_at,
            ]);

        // Filter/paginasi di sisi publik dimuat lewat fetch() (lihat public/home.blade.php)
        // supaya tidak memicu reload halaman penuh — reload penuh bikin scroll balik ke atas
        // dulu (memutar ulang animasi AOS di seluruh halaman) sebelum melompat lagi ke
        // #daftar-pengaduan, yang terasa seperti flicker. Untuk request AJAX ini, cukup
        // kembalikan fragmen daftarnya saja — tidak perlu hitung ulang statistik/grafik
        // yang tidak berubah.
        if ($request->ajax()) {
            return view('public.partials.report-list', ['reports' => $reports]);
        }

        // Semua angka publik — kartu ringkas di atas, distribusi status, dan total di
        // bagian statistik — HARUS lewat $visible (bukan query mentah ke Report::) dan
        // definisi "Dalam Proses" HARUS sama persis dengan admin dashboard
        // (terverifikasi_admin + dalam_penanganan, TIDAK termasuk baru_masuk).
        // Sebelumnya dua bug bikin angka tidak sinkron dengan sisi admin:
        // (1) total/selesai/dalam_proses dihitung dari SEMUA laporan termasuk Red Code,
        // padahal laporan Red Code sengaja disembunyikan dari daftar & grafik publik —
        // begitu ada laporan Red Code, "Total Laporan Masuk" akan lebih besar dari
        // jumlah yang benar-benar bisa dilihat publik di bawahnya.
        // (2) "Dalam Proses" publik ikut menghitung laporan "Baru Masuk" yang belum
        // diverifikasi, padahal di dashboard admin "Baru Masuk" adalah kategori
        // terpisah dari "Dalam Proses" — jadi angka "Dalam Proses" antara sisi publik
        // dan admin bisa berbeda untuk data yang sama.
        $stats = [
            'total' => (clone $visible)->count(),
            'selesai' => (clone $visible)->where('status', 'selesai')->count(),
            'dalam_proses' => (clone $visible)->whereIn('status', [
                'terverifikasi_admin', 'dalam_penanganan',
            ])->count(),
        ];

        return view('public.home', [
            'stats' => $stats,
            'monthly' => $this->monthlyCounts(),
            'topCategories' => (clone $visible)
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'statusDistribution' => $this->publicStatusDistribution($visible),
            'publicTotal' => $stats['total'],
            'reports' => $reports,
            'categories' => (clone $visible)->distinct()->pluck('category'),
            'filters' => $request->only(['status', 'category']),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{status:string, total:int}>
     */
    private function publicStatusDistribution($visible)
    {
        $rawCounts = (clone $visible)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(Report::PUBLIC_STATUS_GROUPS)
            ->map(fn ($statuses, $group) => [
                'status' => Report::PUBLIC_STATUS_LABELS[$group],
                'total' => collect($statuses)->sum(fn ($s) => $rawCounts->get($s, 0)),
            ])
            ->filter(fn ($row) => $row['total'] > 0)
            ->values();
    }

    /**
     * @return array{labels: array<int,string>, counts: array<int,int>}
     */
    private function monthlyCounts(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $counts = Report::publiclyVisible()
            ->where('created_at', '>=', $months->first())
            ->get(['created_at'])
            ->groupBy(fn (Report $r) => $r->created_at->format('Y-m'))
            ->map->count();

        return [
            'labels' => $months->map(fn (Carbon $m) => $m->translatedFormat('M Y'))->all(),
            'counts' => $months->map(fn (Carbon $m) => $counts->get($m->format('Y-m'), 0))->all(),
        ];
    }
}
