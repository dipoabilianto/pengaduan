<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAiSettingsRequest;
use App\Jobs\ScoreReportUrgencyJob;
use App\Models\Report;
use App\Services\AiHealthService;
use App\Services\AiSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AiSettingsController extends Controller
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly AiHealthService $health,
    ) {
    }

    public function edit(): View
    {
        return view('admin.settings.ai', [
            'provider' => $this->settings->provider(),
            'model' => $this->settings->model(),
            'maskedApiKey' => $this->settings->maskedApiKey(),
            'isConfigured' => $this->settings->isConfigured(),
        ]);
    }

    public function update(UpdateAiSettingsRequest $request): RedirectResponse
    {
        $wasConfigured = $this->settings->isConfigured();

        $this->settings->save($request->validated(), $request->user());

        $status = 'Pengaturan integrasi AI berhasil disimpan.';

        // AI baru saja aktif (mis. setelah sempat off) — laporan yang selama ini
        // dinilai manual karena AI tidak tersedia otomatis diambil alih lagi oleh AI.
        if (! $wasConfigured && $this->settings->isConfigured()) {
            $pending = Report::whereDoesntHave('aiAssessment')->get();

            $pending->each(fn (Report $report) => ScoreReportUrgencyJob::dispatch($report));

            if ($pending->isNotEmpty()) {
                $status .= " {$pending->count()} laporan tanpa rekomendasi AI sedang dinilai ulang di latar belakang.";
            }
        }

        return back()->with('status', $status);
    }

    /**
     * Polled by the "AI Otomatis" badge (navigation) so it reflects the current AI
     * condition live, without a full page reload.
     */
    public function health(): JsonResponse
    {
        return response()->json($this->health->status());
    }
}
