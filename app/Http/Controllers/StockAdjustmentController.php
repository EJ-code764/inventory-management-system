<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', StockAdjustment::class);

        return view('adjustments.index');
    }

    public function create(): View
    {
        Gate::authorize('create', StockAdjustment::class);

        return view('adjustments.create');
    }

    public function show(StockAdjustment $stock_adjustment): View
    {
        Gate::authorize('view', $stock_adjustment);

        return view('adjustments.show', ['adjustment' => $stock_adjustment->load(['warehouse', 'creator', 'items.stockItem.product', 'items.stockItem.variant.product', 'movements'])]);
    }
}
