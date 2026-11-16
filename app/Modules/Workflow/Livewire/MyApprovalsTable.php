<?php

namespace App\Modules\Workflow\Livewire;

use App\Core\Exports\ArrayExport;
use App\Models\User;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Workflow → My Approvals: what the logged-in user must answer (default) or has answered.
 * No permission needed: everyone sees only their own rows.
 *
 * View: app/Modules/Workflow/resources/views/livewire/my-approvals-table.blade.php
 */
class MyApprovalsTable extends Component
{
    use WithPagination;

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Show": '' = all */
    #[Url(as: 'show', except: '')]
    public string $show = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['level', 'created_at'];

    /** @var array<int, string> users.id => name, so each name is looked up once per render */
    private array $userNames = [];

    public function subject(Approval $approval): string
    {
        return $approval->approvable instanceof Approvable ? $approval->approvable->approvalSubject() : __('(deleted)');
    }

    public function userName(?int $id): string
    {
        if ($id === null) {
            return '-';
        }

        return $this->userNames[$id] ??= User::find($id)->name ?? __('Deleted user');
    }

    #[On('approval-answered')]
    public function refreshList(): void
    {
        // Re-renders the list.
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
        $this->reset('show');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Approval $approval) => [
            $approval->step->workflow->approvalType->name,
            $this->subject($approval),
            $this->userName($approval->requester_user_id),
            $approval->level,
            format_datetime($approval->created_at),
            $approval->status->label(),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Type'), __('Subject'), __('Requested by'), __('Level'), __('Asked'), __('Status')], $rows),
            'my-approvals-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['show', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Approval> */
    private function query(): Builder
    {
        $me = (int) auth()->id();

        $query = Approval::query()->with(['approvable', 'step.workflow.approvalType']);

        return ($this->show ?: 'waiting') === 'answered'
            ? $query->where('responded_by', $me)
            : $query->where('approver_user_id', $me)->where('status', ApprovalStatus::Pending);
    }

    /** @return array<int|string, string> choices of the "Show" filter */
    private function showOptions(): array
    {
        return ['waiting' => __('Waiting for me'), 'answered' => __('Answered by me')];
    }

    /** @return Builder<Approval> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        // Filter "show" is applied in query().

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('workflow::livewire.my-approvals-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'showOptions' => $this->showOptions(),
        ]);
    }
}
