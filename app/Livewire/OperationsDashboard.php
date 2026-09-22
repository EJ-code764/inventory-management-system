<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Warehouse;
use App\Services\ReportQuery;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class OperationsDashboard extends Component
{
    #[Url]
    public string $warehouse = '';

    #[Url]
    public string $category = '';

    public function updated(): void
    {
        $this->resetValidation();
    }

    public function render(): View
    {
        Gate::authorize('view-report', 'dashboard');
        $filters = $this->only(['warehouse', 'category']);
        $validator = Validator::make($filters, [
            'warehouse' => ['nullable', 'integer', 'exists:warehouses,id'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
        ]);
        $data = null;
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());
        } else {
            $data = app(ReportQuery::class)->dashboard($filters);
        }

        return view('livewire.operations-dashboard', [
            'data' => $data, 'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
