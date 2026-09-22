<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Http\Requests\SupplierStatusRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Supplier::class);

        return view('suppliers.index');
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('suppliers.form', ['supplier' => new Supplier]);
    }

    public function store(SupplierRequest $request, SupplierService $service): RedirectResponse
    {
        $supplier = $service->save($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier created.');
    }

    public function show(Supplier $supplier): View
    {
        Gate::authorize('view', $supplier);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('update', $supplier);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier, SupplierService $service): RedirectResponse
    {
        $service->save($request->validated(), $supplier);

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier updated.');
    }

    public function status(SupplierStatusRequest $request, Supplier $supplier, SupplierService $service): RedirectResponse
    {
        $service->save($request->validated(), $supplier);

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier status updated.');
    }

    public function destroy(Supplier $supplier, SupplierService $service): RedirectResponse
    {
        Gate::authorize('delete', $supplier);
        $service->delete($supplier);

        return redirect()->route('suppliers.index')->with('status', 'Supplier deleted.');
    }
}
