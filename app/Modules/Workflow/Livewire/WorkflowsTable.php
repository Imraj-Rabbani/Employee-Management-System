<?php

namespace App\Modules\Workflow\Livewire;

use App\Core\Exports\ArrayExport;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Workflow → Workflow Setup → Workflows tab. */
class WorkflowsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Type": '' = all */
    #[Url(as: 'approval_type_id', except: '')]
    public string $approvalTypeId = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['name', 'steps_count', 'is_active'];

    /** @var list<string> ids of the ticked rows (the checkboxes). Edit needs one; Delete / Request approval any number. */
    public array $selected = [];

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /** Filter form → Apply: the new filter values came with this click; start again at page 1. */
    public function applyFilters(): void
    {
        $this->resetPage();
        $this->selected = [];
        Flux::modal('workflow-filters')->close();
    }

    #[On('workflow-saved')]
    public function refreshList(): void
    {
        $this->selected = [];   // re-renders the list, nothing ticked
    }

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        // Clicking the same column again flips the direction.
        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'approvalTypeId');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('workflow-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Workflow $workflow) => [
            $workflow->name,
            $workflow->approvalType->name,
            collect([
                $workflow->scopeEntity?->name,
                $workflow->scopeEmploymentType?->name,
                $workflow->min_amount !== null ? __('from :amount', ['amount' => number_format((float) $workflow->min_amount, 2)]) : null,
            ])->filter()->implode(' · ') ?: __('Everything'),
            $workflow->steps_count,
            $workflow->send_email ? __('Yes') : __('No'),
            $workflow->is_active ? __('Active') : __('Inactive'),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Workflow'), __('Type'), __('Applies to'), __('Levels'), __('Email'), __('Active')], $rows),
            'workflows-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'approvalTypeId', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Workflow> */
    private function query(): Builder
    {
        return Workflow::query()->with(['approvalType', 'scopeEntity', 'scopeEmploymentType'])->withCount('steps');
    }

    /** @return array<int|string, string> choices of the "Type" filter */
    private function approvalTypeIdOptions(): array
    {
        return ApprovalType::orderBy('name')->pluck('name', 'id')->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all();
    }

    /** @return Builder<Workflow> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like));
        }

        if ($this->approvalTypeId !== '' && array_key_exists($this->approvalTypeId, $this->approvalTypeIdOptions())) {
            $query->where('approval_type_id', $this->approvalTypeId);
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('approval_type_id', 'asc');
            $query->orderBy('name', 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('workflow::livewire.workflows-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'approvalTypeIdOptions' => $this->approvalTypeIdOptions(),
        ]);
    }
}
