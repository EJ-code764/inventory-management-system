<?php

namespace App\Livewire;

use App\Http\Requests\ActivityLogFilterRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityLogTable extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $user = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function updated(): void
    {
        $this->resetPage();
        $this->resetValidation();
    }

    public function clearFilters(): void
    {
        Gate::authorize('viewAny', ActivityLog::class);
        $this->reset(['search', 'user', 'action', 'from', 'to']);
        $this->resetPage();
        $this->resetValidation();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', ActivityLog::class);
        $rules = (new ActivityLogFilterRequest)->rules();
        if ($this->from !== '') {
            $rules['to'][] = 'after_or_equal:from';
        }
        $validator = Validator::make($this->only(['search', 'user', 'action', 'from', 'to']), $rules);
        $logs = null;
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());
        } else {
            $search = '%'.trim($this->search).'%';
            $logs = ActivityLog::with('causer:id,name')
                ->when($this->search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                    $query->where('event', 'like', $search)->orWhere('subject_type', 'like', $search)
                        ->orWhere('subject_id', 'like', $search)->orWhere('properties', 'like', $search)
                        ->orWhereHas('causer', fn (Builder $user): Builder => $user->where('name', 'like', $search));
                }))
                ->when($this->user === 'system', fn (Builder $query): Builder => $query->whereNull('causer_id'))
                ->when($this->user !== '' && $this->user !== 'system', fn (Builder $query): Builder => $query->where('causer_id', $this->user))
                ->when($this->action !== '', fn (Builder $query): Builder => $query->where('event', $this->action))
                ->when($this->from !== '', fn (Builder $query): Builder => $query->where('created_at', '>=', $this->from))
                ->when($this->to !== '', fn (Builder $query): Builder => $query->where('created_at', '<', Carbon::parse($this->to)->addDay()->toDateString()))
                ->latest('created_at')->latest('id')->paginate(25);
        }

        return view('livewire.activity-log-table', [
            'logs' => $logs, 'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => ActivityLog::select('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }
}
