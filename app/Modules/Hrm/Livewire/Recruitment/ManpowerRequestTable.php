<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Enums\ManpowerRequestType;
use App\Modules\Hrm\Models\ManpowerRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HRM → Recruitment → Manpower requests: the list.
 * Only requests of the org units the user can see (ManpowerRequest::visibleTo). Click the number to open its page.
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
    protected $listeners = ['manpower-request-saved' => '$refresh'];

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
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (ManpowerRequest $manpowerRequest) => [
            $manpowerRequest->number(),
            $manpowerRequest->designation->name ?? '-',
            $manpowerRequest->orgUnit->name ?? '-',
            $manpowerRequest->type->label(),
            $manpowerRequest->headcount,
            $manpowerRequest->needed_by ? format_date($manpowerRequest->needed_by) : '-',
            $manpowerRequest->approvalStatus()?->label() ?? __('Draft'),
            format_date($manpowerRequest->created_at),
        ])->all();

        return Excel::download(
            new ArrayExport([__('No.'), __('Designation'), __('Org unit'), __('Type'), __('Headcount'), __('Needed by'), __('Status'), __('Created')], $rows),
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
            ->with(['designation', 'orgUnit', 'approvals']);
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
