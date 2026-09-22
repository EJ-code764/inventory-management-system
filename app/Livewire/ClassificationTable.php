<?php

namespace App\Livewire;

use App\Services\ClassificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ClassificationTable extends Component
{
    use WithPagination;

    #[Locked]
    public string $resource;

    #[Url]
    public string $search = '';

    public function mount(string $resource): void
    {
        $this->resource = $resource;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $model = ClassificationService::modelFor($this->resource);
        Gate::authorize('viewAny', [$model, $this->resource]);
        $search = mb_substr(trim($this->search), 0, 255);
        $records = $model::query()->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->paginate(10);

        return view('livewire.classification-table', compact('records', 'model'));
    }
}
