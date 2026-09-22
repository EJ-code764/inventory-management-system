<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseRequest;
use App\Http\Requests\WarehouseStatusRequest;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Warehouse::class);

        return view('warehouses.index');
    }

    public function create(): View
    {
        Gate::authorize('create', Warehouse::class);

        return view('warehouses.form', ['warehouse' => new Warehouse]);
    }

    public function store(WarehouseRequest $request, WarehouseService $service): RedirectResponse
    {
        $warehouse = $service->save($request->validated());

        return redirect()->route('warehouses.show', $warehouse)->with('status', 'Warehouse created.');
    }

    public function show(Warehouse $warehouse): View
    {
        Gate::authorize('view', $warehouse);

        return view('warehouses.show', compact('warehouse'));
    }

    public function edit(Warehouse $warehouse): View
    {
        Gate::authorize('update', $warehouse);

        return view('warehouses.form', compact('warehouse'));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse, WarehouseService $service): RedirectResponse
    {
        $service->save($request->validated(), $warehouse);

        return redirect()->route('warehouses.show', $warehouse)->with('status', 'Warehouse updated.');
    }

    public function status(WarehouseStatusRequest $request, Warehouse $warehouse, WarehouseService $service): RedirectResponse
    {
        $service->save($request->validated(), $warehouse);

        return redirect()->route('warehouses.show', $warehouse)->with('status', 'Warehouse status updated.');
    }
}
