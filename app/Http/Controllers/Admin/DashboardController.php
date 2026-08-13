<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\AiHealthService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AiHealthService $aiHealth)
    {
    }

    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Report::class);

        // Dashboard adalah ringkasan agregat organisasi, BUKAN daftar kerja milik satu
        // role/pengguna — jadi sengaja TIDAK dibatasi visibleTo($user) seperti daftar
        // laporan & ekspor. "Laporan mana yang boleh ditangani siapa" itu urusan halaman
        // Laporan (work queue, tetap dibatasi lewat scopeVisibleTo di sana); dashboard
        // menampilkan gambaran umum yang sama untuk siapa pun yang berhak membuka
        // halaman ini sama sekali, supaya tidak menyesatkan (mis. Pelaksana dengan 3
        // laporan yang ditugaskan ke dirinya tidak melihat total organisasi seolah cuma 3).
        $all = Report::query();

        $statusCounts = $this->countsByStatus($all);

        return view('admin.dashboard', [
            'stats' => [
                'total' => (clone $all)->count(),
                'baru_masuk' => $statusCounts['baru_masuk'],
                'dalam_proses' => (clone $all)->whereIn('status', [
                    'terverifikasi_admin', 'dalam_penanganan',
                ])->count(),
                'selesai' => $statusCounts['selesai'],
                'ditolak' => $statusCounts['ditolak'],
            ],
            'statusCounts' => $statusCounts,
            'monthly' => $this->monthlyCounts($all),
            'topCategories' => (clone $all)
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'urgencyDistribution' => $this->urgencyDistribution($all),
            'aiStatus' => $this->aiHealth->status(),
        ]);
    }

    /**
     * @return array<string,int> keyed like Report::STATUS_LABELS
     */
    private function countsByStatus(Builder $all): array
    {
        $counts = (clone $all)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(Report::STATUS_LABELS)
            ->keys()
            ->mapWithKeys(fn (string $status) => [$status => $counts->get($status, 0)])
            ->all();
    }

    /**
     * @return array{labels: array<int,string>, counts: array<int,int>}
     */
    private function monthlyCounts(Builder $visible): array
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $counts = (clone $visible)
            ->where('created_at', '>=', $months->first())
            ->get(['created_at'])
            ->groupBy(fn (Report $r) => $r->created_at->format('Y-m'))
            ->map->count();

        return [
            'labels' => $months->map(fn (Carbon $m) => $m->translatedFormat('M Y'))->all(),
            'counts' => $months->map(fn (Carbon $m) => $counts->get($m->format('Y-m'), 0))->all(),
        ];
    }

    /**
     * Unlike the public dashboard, admin sees every urgency level including Red Code —
     * nothing is hidden here (Bab 4.3/7 PDR only restricts the *public* views).
     *
     * @return Collection<int, array{label:string, total:int}>
     */
    private function urgencyDistribution(Builder $visible): Collection
    {
        $counts = (clone $visible)
            ->whereNotNull('urgency_flag')
            ->selectRaw('urgency_flag, count(*) as total')
            ->groupBy('urgency_flag')
            ->pluck('total', 'urgency_flag');

        return collect(Report::URGENCY_LABELS)
            ->map(fn (string $label, string $flag) => ['flag' => $flag, 'label' => $label, 'total' => $counts->get($flag, 0)])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }
}
