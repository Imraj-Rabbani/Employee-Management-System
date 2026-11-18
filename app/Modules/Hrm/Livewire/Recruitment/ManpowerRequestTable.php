<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Enums\ManpowerRequestType;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HRM → Recruitment → Manpower requests: the list.
 * Only requests of the entities the user can see (ManpowerRequest::visibleTo). Click the number to open its page.
 *
 * View: app/Modules/Hrm/resources/views/livewire/recruitment/manpower-request-table.blade.php
 */
class ManpowerRequestTable extends Component
{
    use WithPagination;

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Type": '' = all */
    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['id', 'type', 'headcount', 'needed_by', 'created_at'];

    /** @var array<string, string> refresh the list when the form saved something */
    protected $listeners = ['manpower-request-saved' => 'clearSelection'];

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
        Flux::modal('manpower-request-filters')->close();
    }

    /**
     * Toolbar → Request approval: sends every ticked request that may go (a draft or one sent back) into its
     * workflow, with the same checks as its own page. One toast says what was sent and what was not, and why.
     */
    public function requestApproval(): void
    {
        Gate::authorize('hrm.recruitment.request');
        $sent = 0;
        $problems = [];
        foreach (ManpowerRequest::query()->visibleTo(Auth::user())->with('approvals')->whereKey($this->selected)->get() as $document) {
            $problem = $document->approvalSendProblem();
            if ($problem === null) {
                try {
                    $document->submitForApproval(Auth::user());
                    $sent++;

                    continue;
                } catch (WorkflowException $e) {
                    $problem = $e->getMessage();   // no workflow, nobody to approve ...: written for users
                }
            }
            $problems[] = $document->number().': '.$problem;
        }

        $this->selected = [];
        $this->dispatch('toast',
            message: implode(' ', array_filter([$sent > 0 ? trans_choice(':count sent for approval.|:count sent for approval.', $sent) : null, ...$problems])),
            variant: $problems === [] ? 'success' : ($sent > 0 ? 'warning' : 'error'),
        );
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
        $this->reset('type');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('manpower-request-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (ManpowerRequest $manpowerRequest) => [
            $manpowerRequest->number(),
            $manpowerRequest->designation->name ?? '-',
            $manpowerRequest->entity->name ?? '-',
            $manpowerRequest->type->label(),
            $manpowerRequest->headcount,
            $manpowerRequest->needed_by ? format_date($manpowerRequest->needed_by) : '-',
            $manpowerRequest->approvalStatus()?->label() ?? __('Draft'),
            format_date($manpowerRequest->created_at),
        ])->all();

        return Excel::download(
            new ArrayExport([__('No.'), __('Designation'), __('Entity'), __('Type'), __('Headcount'), __('Needed by'), __('Status'), __('Created')], $rows),
            'manpower-request-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['type', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<ManpowerRequest> */
    private function query(): Builder
    {
        // 'approvals' eager loaded: the Status column calls approvalStatus() on every row.
        return ManpowerRequest::query()
            ->visibleTo(Auth::user())
            ->with(['designation', 'entity', 'approvals']);
    }

    /** @return array<int|string, string> choices of the "Type" filter */
    private function typeOptions(): array
    {
        return ManpowerRequestType::options();
    }

    /** @return Builder<ManpowerRequest> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if ($this->type !== '' && array_key_exists($this->type, $this->typeOptions())) {
            $query->where('type', $this->type);
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('hrm::livewire.recruitment.manpower-request-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'typeOptions' => $this->typeOptions(),
        ]);
    }
}
