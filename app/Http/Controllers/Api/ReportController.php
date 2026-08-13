<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignReportRequest;
use App\Http\Requests\Api\UpdateReportStatusRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

/**
 * Bab 6.1 PDR: backend untuk aplikasi Android companion Admin/Pejabat. Setiap aksi
 * memakai ulang otorisasi (ReportPolicy) dan logika bisnis (ReportAdminService) yang
 * sama persis dengan sisi web — tidak ada state machine/aturan yang diduplikasi di sini.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportAdminService $adminService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Report::class);

        $reports = Report::query()->visibleTo($request->user())->latest()->paginate(20);

        return ReportResource::collection($reports);
    }

    public function show(Report $report): ReportResource
    {
        $this->authorize('view', $report);

        return new ReportResource($report);
    }

    public function updateStatus(UpdateReportStatusRequest $request, Report $report): ReportResource|JsonResponse
    {
        try {
            $updated = $this->adminService->updateStatus(
                $report,
                $request->validated('status'),
                $request->validated('urgency_flag'),
                $request->user(),
                $request->validated('note'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ReportResource($updated);
    }

    public function assign(AssignReportRequest $request, Report $report): ReportResource|JsonResponse
    {
        $pejabat = User::findOrFail($request->validated('pejabat_id'));

        try {
            $updated = $this->adminService->assignToPejabat($report, $pejabat, $request->user(), $request->validated('note'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ReportResource($updated);
    }
}
