<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatusCheckController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function form(): View
    {
        return view('public.status.form');
    }

    public function result(Request $request): View
    {
        $request->validate([
            'query' => ['required', 'string', 'max:50'],
        ]);

        $reports = $this->reports->findByTicketOrPhone($request->string('query')->trim()->toString());

        return view('public.status.result', [
            'reports' => $reports,
            'query' => $request->input('query'),
        ]);
    }
}
