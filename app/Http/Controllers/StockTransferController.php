<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockTransferStatusRequest;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\StockTransferStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', StockTransfer::class);

        return view('transfers.index');
    }

    public function create(): View
    {
        Gate::authorize('create', StockTransfer::class);

        return view('transfers.form', ['transfer' => null]);
    }

    public function edit(StockTransfer $stock_transfer): View
    {
        Gate::authorize('update', $stock_transfer);
        abort_unless($stock_transfer->status === StockTransferStatus::Draft, 409, 'Only draft transfers can be edited.');

        return view('transfers.form', ['transfer' => $stock_transfer]);
    }

    public function show(StockTransfer $stock_transfer): View
    {
        Gate::authorize('view', $stock_transfer);

        return view('transfers.show', ['transfer' => $stock_transfer->load(['sourceWarehouse', 'destinationWarehouse', 'creator', 'processor', 'items.stockItem.product', 'items.stockItem.variant.product', 'movements.warehouse', 'movements.stockItem'])]);
    }

    public function status(StockTransferStatusRequest $request, StockTransfer $stock_transfer, StockTransferService $service): RedirectResponse
    {
        $service->transition($stock_transfer, StockTransferStatus::from($request->validated('status')), $request->user(), (int) $request->validated('revision'));

        return redirect()->route('stock-transfers.show', $stock_transfer)->with('status', 'Transfer status updated.');
    }
}
