<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function create(): View
    {
        return view('public.reports.create');
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $report = $this->reports->submit(
            $request->safe()->except(['attachments', 'g-recaptcha-response']),
            $request->file('attachments', []),
        );

        return redirect()
            ->route('report.success', $report->ticket_no);
    }

    public function success(string $ticketNo): View
    {
        $report = Report::where('ticket_no', $ticketNo)->firstOrFail();

        return view('public.reports.success', ['report' => $report]);
    }
}
