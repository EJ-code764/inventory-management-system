<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderStatusRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\PurchaseOrderStatus;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        return view('purchases.index');
    }

    public function create(): View
    {
        Gate::authorize('create', PurchaseOrder::class);

        return view('purchases.form', ['order' => null]);
    }

    public function edit(PurchaseOrder $purchase_order): View
    {
        Gate::authorize('update', $purchase_order);
        abort_unless($purchase_order->status === PurchaseOrderStatus::Draft, 409, 'Only draft orders can be edited.');

        return view('purchases.form', ['order' => $purchase_order]);
    }

    public function show(PurchaseOrder $purchase_order): View
    {
        Gate::authorize('view', $purchase_order);

        return view('purchases.show', ['order' => $purchase_order->load(['supplier', 'warehouse', 'creator', 'items.stockItem.product', 'items.stockItem.variant.product', 'receipts.receiver'])]);
    }

    public function status(PurchaseOrderStatusRequest $request, PurchaseOrder $purchase_order, PurchaseOrderService $service): RedirectResponse
    {
        $service->transition($purchase_order, PurchaseOrderStatus::from($request->validated('status')), $request->user());

        return redirect()->route('purchase-orders.show', $purchase_order)->with('status', 'Purchase order status updated.');
    }

    public function receive(PurchaseOrder $purchase_order): View
    {
        Gate::authorize('receive', $purchase_order);
        abort_unless(in_array($purchase_order->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true), 409, 'This order is not available for receiving.');

        return view('purchases.receive', ['order' => $purchase_order]);
    }

    public function receipt(PurchaseOrder $purchase_order, PurchaseReceipt $receipt): View
    {
        Gate::authorize('view', $purchase_order);
        abort_unless($receipt->purchase_order_id === $purchase_order->id, 404);

        return view('purchases.receipt', ['receipt' => $receipt->load(['purchaseOrder', 'warehouse', 'receiver', 'items.stockItem.product', 'items.stockItem.variant.product'])]);
    }
}
