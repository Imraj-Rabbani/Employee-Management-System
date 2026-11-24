<?php

namespace App\Modules\Workflow\Livewire;

use App\Core\Exports\ArrayExport;
use App\Modules\Workflow\Models\ApprovalType;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Workflow → Workflow Setup: one row per approval type the code offers (Leave, Manpower request ...),
 * with its levels. Opening a row configures that type's workflow (WorkflowForm). No "Add new" /
 * "Delete": the types come from the code, not from this screen.
 */
class WorkflowsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Status": '' = all, 'active', 'off', 'none' (not set up yet) */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['name'];

    private const STATUSES = ['active', 'off', 'none'];

    /** @var list<string> ids (approval type ids) of the ticked rows. Edit needs exactly one. */
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
        $this->reset('search', 'status');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('workflow-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (ApprovalType $type) => [
            $type->name,
            $type->workflow?->levelsText() ?? '',
            $type->workflow === null ? '' : ($type->workflow->send_email ? __('Yes') : __('No')),
            $type->workflowStatusLabel(),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Workflow'), __('Levels'), __('Email'), __('Status')], $rows),
            'workflows-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Active approval types (inactive ones are switched off in the code) with their workflow and
     * everything describe() needs for the levels column.
     *
     * @return Builder<ApprovalType>
     */
    private function filteredQuery(): Builder
    {
        $query = ApprovalType::query()
            ->where('is_active', true)
            ->with(['workflow.steps.approverEntityType', 'workflow.steps.approverEntity', 'workflow.steps.approverEmployee']);

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $query->where('name', 'like', '%'.addcslashes(trim($this->search), '%_\\').'%');
        }

        match (in_array($this->status, self::STATUSES, true) ? $this->status : '') {
            'active' => $query->whereHas('workflow', fn (Builder $q) => $q->where('is_active', true)->has('steps')),
            'off' => $query->whereHas('workflow', fn (Builder $q) => $q->where('is_active', false)),
            'none' => $query->where(fn (Builder $q) => $q->doesntHave('workflow')
                ->orWhereHas('workflow', fn (Builder $w) => $w->where('is_active', true)->doesntHave('steps'))),
            default => null,
        };

        return $query->orderBy('name', $this->sortBy === 'name' && $this->sortDir === 'desc' ? 'desc' : 'asc');
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 25.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;

        return view('workflow::livewire.workflows-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
