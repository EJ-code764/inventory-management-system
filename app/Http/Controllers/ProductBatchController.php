<?php

namespace App\Http\Controllers;

use App\Models\ProductBatch;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductBatchController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ProductBatch::class);

        return view('batches.index');
    }
}
