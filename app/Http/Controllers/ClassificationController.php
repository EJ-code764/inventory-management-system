<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassificationRequest;
use App\Services\ClassificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClassificationController extends Controller
{
    public function index(string $resource): View
    {
        Gate::authorize('viewAny', [ClassificationService::modelFor($resource), $resource]);

        return view('classifications.index', compact('resource'));
    }

    public function create(string $resource): View
    {
        $model = ClassificationService::modelFor($resource);
        Gate::authorize('create', [$model, $resource]);

        return view('classifications.form', ['resource' => $resource, 'record' => new $model(['status' => 'active'])]);
    }

    public function store(ClassificationRequest $request, string $resource): RedirectResponse
    {
        $model = ClassificationService::modelFor($resource);
        $model::create($request->validated());

        return redirect()->route($resource.'.index')->with('status', 'Record created.');
    }

    public function show(string $record, string $resource): View
    {
        $record = ClassificationService::modelFor($resource)::findOrFail($record);
        Gate::authorize('view', $record);

        return view('classifications.show', compact('resource', 'record'));
    }

    public function edit(string $record, string $resource): View
    {
        $record = ClassificationService::modelFor($resource)::findOrFail($record);
        Gate::authorize('update', $record);

        return view('classifications.form', compact('resource', 'record'));
    }

    public function update(ClassificationRequest $request, string $record, string $resource): RedirectResponse
    {
        $request->record()->update($request->validated());

        return redirect()->route($resource.'.index')->with('status', 'Record updated.');
    }

    public function status(Request $request, string $record, string $resource): RedirectResponse
    {
        $record = ClassificationService::modelFor($resource)::findOrFail($record);
        Gate::authorize('update', $record);
        $record->update($request->validate(['status' => ['required', 'string', 'in:active,inactive']]));

        return redirect()->route($resource.'.index')->with('status', 'Status updated.');
    }

    public function destroy(ClassificationService $service, string $record, string $resource): RedirectResponse
    {
        $record = ClassificationService::modelFor($resource)::findOrFail($record);
        Gate::authorize('delete', $record);
        $service->delete($record);

        return redirect()->route($resource.'.index')->with('status', 'Record deleted.');
    }
}
