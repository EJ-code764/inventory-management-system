<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductBatchController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/product-batches', [ProductBatchController::class, 'index'])->name('product-batches.index');
    Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'create', 'show', 'edit']);
    Route::patch('/stock-transfers/{stock_transfer}/status', [StockTransferController::class, 'status'])->name('stock-transfers.status');
    Route::resource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'create', 'show']);
    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'show', 'edit']);
    Route::patch('/purchase-orders/{purchase_order}/status', [PurchaseOrderController::class, 'status'])->name('purchase-orders.status');
    Route::get('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
    Route::get('/purchase-orders/{purchase_order}/receipts/{receipt}', [PurchaseOrderController::class, 'receipt'])->name('purchase-orders.receipt');
    Route::resource('suppliers', SupplierController::class);
    Route::patch('/suppliers/{supplier}/status', [SupplierController::class, 'status'])->name('suppliers.status');
    Route::get('/inventory/dashboard', [InventoryController::class, 'dashboard'])->name('inventory.dashboard');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::get('/inventory/items/{stockItem}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::resource('warehouses', WarehouseController::class)->except('destroy');
    Route::patch('/warehouses/{warehouse}/status', [WarehouseController::class, 'status'])->name('warehouses.status');
    Route::resource('products', ProductController::class)->except('destroy');
    Route::patch('/products/{product}/status', [ProductController::class, 'status'])->name('products.status');
    foreach (['categories', 'brands', 'units'] as $resource) {
        Route::get('/'.$resource, [ClassificationController::class, 'index'])->defaults('resource', $resource)->name($resource.'.index');
        Route::get('/'.$resource.'/create', [ClassificationController::class, 'create'])->defaults('resource', $resource)->name($resource.'.create');
        Route::post('/'.$resource, [ClassificationController::class, 'store'])->defaults('resource', $resource)->name($resource.'.store');
        Route::get('/'.$resource.'/{record}', [ClassificationController::class, 'show'])->whereNumber('record')->defaults('resource', $resource)->name($resource.'.show');
        Route::get('/'.$resource.'/{record}/edit', [ClassificationController::class, 'edit'])->whereNumber('record')->defaults('resource', $resource)->name($resource.'.edit');
        Route::put('/'.$resource.'/{record}', [ClassificationController::class, 'update'])->whereNumber('record')->defaults('resource', $resource)->name($resource.'.update');
        Route::patch('/'.$resource.'/{record}/status', [ClassificationController::class, 'status'])->whereNumber('record')->defaults('resource', $resource)->name($resource.'.status');
        Route::delete('/'.$resource.'/{record}', [ClassificationController::class, 'destroy'])->whereNumber('record')->defaults('resource', $resource)->name($resource.'.destroy');
    }
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
