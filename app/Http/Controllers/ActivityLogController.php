<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ActivityLog::class);

        return view('activity-logs.index');
    }
}
