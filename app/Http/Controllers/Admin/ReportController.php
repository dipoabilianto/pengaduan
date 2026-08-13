<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveAiAssessmentRequest;
use App\Http\Requests\Admin\AssignReportRequest;
use App\Http\Requests\Admin\GenerateReportReplyRequest;
use App\Http\Requests\Admin\RescoreWithAiRequest;
use App\Http\Requests\Admin\RevealReporterRequest;
use App\Http\Requests\Admin\UpdateReportReplyRequest;
use App\Http\Requests\Admin\UpdateReportStatusRequest;
use App\Jobs\ScoreReportUrgencyJob;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\User;
use App\Services\Ai\AiClientFactory;
use App\Services\AiSettingsService;
use App\Services\ReportAdminService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportAdminService $adminService,
        private readonly AiSettingsService $aiSettings,
        private readonly AiClientFactory $aiClientFactory,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Report::class);

        return view('admin.reports.index', [
            'reports' => $this->filteredQuery($request)->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['type', 'category', 'status', 'urgency_flag', 'channel', 'date_from', 'date_to']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Report::class);

        $export = new ReportsExport($this->filteredQuery($request)->latest());

        return $export->download('laporan-'.now()->format('Y-m-d-His').'.xlsx');
    }

    /**
     * Query shared by the reports list and Excel export so the visibility rule
     * (Report::scopeVisibleTo — per-role status & assigned-only permissions) and filter
     * chain never drift between them.
     */
    private function filteredQuery(Request $request): Builder
    {
        return Report::query()
            ->visibleTo($request->user())
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('urgency_flag'), fn ($q) => $q->where('urgency_flag', $request->input('urgency_flag')))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->input('channel')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')));
    }

    public function show(Report $report): View
    {
        $this->authorize('view', $report);

        $report->load(['attachments', 'statusLogs.actor', 'assignments.assignee', 'assignments.assigner', 'aiAssessment']);

        return view('admin.reports.show', [
            'report' => $report,
            'pejabatList' => User::permission('laporan.terima-pelimpahan')->orderBy('name')->get(),
            'canAssign' => auth()->user()->can('assign', $report),
            'canRevealReporter' => auth()->user()->can('viewReporterIdentity', $report),
            'canApproveAi' => auth()->user()->can('approveAi', $report),
            'isAiConfigured' => $this->aiSettings->isConfigured(),
        ]);
    }

    public function showAttachment(Report $report, ReportAttachment $attachment): Response
    {
        $this->authorize('view', $report);

        $contents = Crypt::decrypt(Storage::disk('local')->get($attachment->file_path));

        return response($contents, 200, ['Content-Type' => $attachment->file_type]);
    }

    public function updateStatus(UpdateReportStatusRequest $request, Report $report): RedirectResponse
    {
        // Routing to a Pejabat for the first time must actually record who it's routed to —
        // go through assignToPejabat() so a report_assignments row is created, not just the
        // bare status flip (which would otherwise leave the report "dalam penanganan" to no one).
        if ($request->validated('status') === 'dalam_penanganan' && $request->filled('pejabat_id')) {
            $pejabat = User::findOrFail($request->validated('pejabat_id'));

            $this->adminService->assignToPejabat($report, $pejabat, $request->user(), $request->validated('note'));

            return back()->with('status', "Laporan diteruskan ke {$pejabat->name}.");
        }

        $changed = $report->wouldChangeWith(
            $request->validated('status'),
            $request->validated('urgency_flag'),
            $request->validated('note'),
        );

        // Reply is saved BEFORE the status transition — if this submission is what finalizes the
        // report (e.g. "Tandai Selesai" with a reply attached), writing it after would have the
        // report's own lock guard reject its own reply as an edit to an already-final report.
        if ($request->filled('reply')) {
            $this->adminService->updateReply($report, $request->user(), $request->validated('reply'));
        }

        $this->adminService->updateStatus(
            $report,
            $request->validated('status'),
            $request->validated('urgency_flag'),
            $request->user(),
            $request->validated('note'),
        );

        return back()->with('status', $changed
            ? 'Status laporan berhasil diperbarui.'
            : 'Tidak ada perubahan — status laporan tetap sama.');
    }

    public function approveAiAssessment(ApproveAiAssessmentRequest $request, Report $report): RedirectResponse
    {
        $finalFlag = $request->validated('final_flag');

        $this->adminService->approveAiAssessment(
            $report,
            $finalFlag,
            $request->user(),
            $request->validated('note'),
            $request->validated('reply'),
        );

        return back()->with('status', $finalFlag === 'tidak_valid'
            ? 'Rekomendasi AI diverifikasi. Laporan berstatus Ditolak.'
            : 'Rekomendasi AI diverifikasi. Laporan berstatus Terverifikasi Admin.');
    }

    /**
     * Manual "ambil alih ulang oleh AI" — for a report that was decided (or is still
     * pending) without an AI opinion because AI was off/unconfigured at the time.
     */
    public function rescoreWithAi(RescoreWithAiRequest $request, Report $report): RedirectResponse
    {
        ScoreReportUrgencyJob::dispatch($report);

        return back()->with('status', 'Penilaian AI sedang diproses. Muat ulang halaman ini sesaat lagi untuk melihat hasilnya.');
    }

    /**
     * Polled by the "Nilai Ulang dengan AI" loading state in admin.reports.show
     * to know when the queued ScoreReportUrgencyJob has finished.
     */
    public function aiStatus(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return response()->json([
            'ready' => $report->aiAssessment()->exists(),
        ]);
    }

    public function assign(AssignReportRequest $request, Report $report): RedirectResponse
    {
        $pejabat = User::findOrFail($request->validated('pejabat_id'));

        $this->adminService->assignToPejabat($report, $pejabat, $request->user(), $request->validated('note'));

        return back()->with('status', "Laporan diteruskan ke {$pejabat->name}.");
    }

    public function revealReporter(RevealReporterRequest $request, Report $report): RedirectResponse
    {
        $identity = $this->adminService->revealReporterIdentity(
            $report,
            $request->user(),
            $request->validated('reason'),
            $request->ip(),
        );

        return back()->with('reporterIdentity', $identity);
    }

    /**
     * A reply is entirely optional — not every report needs one, and it can be edited
     * or removed (deleteReply) freely, since it's a courtesy message, not an audit record.
     */
    public function updateReply(UpdateReportReplyRequest $request, Report $report): RedirectResponse
    {
        $this->adminService->updateReply($report, $request->user(), $request->validated('reply'));

        return back()->with('status', 'Balasan untuk pelapor tersimpan.');
    }

    public function deleteReply(Report $report): RedirectResponse
    {
        $this->authorize('updateStatus', $report);

        $this->adminService->deleteReply($report, auth()->user());

        return back()->with('status', 'Balasan untuk pelapor dihapus.');
    }

    /**
     * On-demand, synchronous — Admin clicks "Buat dengan AI" and gets a fresh, differently
     * worded draft each time (Bab 5.3: still just a recommendation, never saved automatically).
     */
    public function generateReply(GenerateReportReplyRequest $request, Report $report): JsonResponse
    {
        $reasoning = $report->aiAssessment?->ai_suggested_flag === 'tidak_valid'
            ? $report->aiAssessment->ai_reasoning
            : null;

        try {
            $reply = $this->aiClientFactory->make()?->generateReply($report, $reasoning);

            if ($reply) {
                $this->aiSettings->recordSuccess('reply');
            }
        } catch (Throwable $e) {
            Log::warning('AI reply generation failed.', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);

            $this->aiSettings->recordFailureFrom($e, 'reply');
            $reply = null;
        }

        if (! $reply) {
            return response()->json([
                'message' => 'Gagal membuat balasan dengan AI. Coba lagi.',
                'errors' => ['reply' => ['Gagal membuat balasan dengan AI. Coba lagi.']],
            ], 422);
        }

        return response()->json(['reply' => $reply]);
    }
}
