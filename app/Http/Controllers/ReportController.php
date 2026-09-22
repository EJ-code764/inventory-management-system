<?php

namespace App\Http\Controllers;

use App\Services\ReportQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $reports = collect(ReportQuery::TYPES)->filter(fn (string $label, string $key): bool => Gate::allows('view-report', $key));
        abort_if($reports->isEmpty(), 403);

        return view('reports.index', compact('reports'));
    }

    public function show(string $report): View
    {
        abort_unless(isset(ReportQuery::TYPES[$report]), 404);
        Gate::authorize('view-report', $report);

        return view('reports.show', ['report' => $report, 'title' => ReportQuery::TYPES[$report]]);
    }
}
