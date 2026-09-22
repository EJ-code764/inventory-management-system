<?php

namespace App\Livewire;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Category;
use App\Models\Warehouse;
use App\Services\ReportQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ReportTable extends Component
{
    use WithPagination;

    #[Locked]
    public string $report;

    #[Url]
    public string $search = '';

    #[Url]
    public string $warehouse = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $period = '30';

    public function mount(string $report): void
    {
        abort_unless(isset(ReportQuery::TYPES[$report]), 404);
        $this->report = $report;
        Gate::authorize('view-report', $report);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'warehouse', 'category', 'from', 'to', 'period'], true)) {
            $this->resetPage();
            $this->resetValidation();
        }
    }

    public function clearFilters(): void
    {
        Gate::authorize('view-report', $this->report);
        $this->reset(['search', 'warehouse', 'category', 'from', 'to', 'period']);
        $this->resetPage();
        $this->resetValidation();
    }

    public function render(): View
    {
        Gate::authorize('view-report', $this->report);
        $filters = $this->only(['search', 'warehouse', 'category', 'from', 'to', 'period']);
        $rules = (new ReportFilterRequest)->rules();
        if ($this->from !== '') {
            $rules['to'][] = 'after_or_equal:from';
        }
        $validator = Validator::make($filters, $rules);
        $rows = null;
        $valuation = null;
        $service = app(ReportQuery::class);
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());
        } else {
            $query = $service->query($this->report, $filters);
            if ($this->report === 'valuation') {
                $valuation = ReportQuery::decimal(DB::query()->fromSub(clone $query, 'values_report')->sum('valuation'));
            }
            if (isset(ReportQuery::DATES[$this->report])) {
                $query->orderByDesc('date')->orderByDesc('report_row_id');
            } else {
                $query->orderBy('stock_items.sku')->orderBy('warehouses.id');
            }
            $rows = $query->paginate(25);
        }

        return view('livewire.report-table', [
            'rows' => $rows, 'valuation' => $valuation, 'columns' => $service->columns($this->report),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
