<?php

namespace App\Modules\Hrm\Livewire\Recruitment\Reports;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Recruitment report: open positions. Every APPROVED manpower request that still has people to hire:
 * how many are wanted, hired, still open, and how many of those are advertised right now.
 *
 * One query for the whole page: "open_count" is worked out by MySQL (headcount − hired_count) and
 * "advertised" with withSum() (Draft / Open postings), so both can be sorted and exported.
 * Only the entities the user can see (ManpowerRequest::visibleTo). Excel export comes from DataTable.
 *
 * View: app/Modules/Hrm/resources/views/livewire/recruitment/reports/open-positions-report.blade.php
 */
class OpenPositionsReport extends Component
{
    use WithPagination;

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Entity": '' = all */
    #[Url(as: 'entity', except: '')]
    public string $entity = '';

    /** Filter "Needed by": '' = all */
    #[Url(as: 'timing', except: '')]
    public string $timing = '';

    /** Filter "Advertised": '' = all */
    #[Url(as: 'advertised', except: '')]
    public string $advertised = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['id', 'headcount', 'hired_count', 'open_count', 'advertised', 'needed_by', 'approved_at'];

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
        $this->reset('entity', 'timing', 'advertised');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (ManpowerRequest $manpowerRequest) => [
            $manpowerRequest->number(),
            $manpowerRequest->designation->name ?? '-',
            $manpowerRequest->entity->name ?? '-',
            $manpowerRequest->type->label(),
            $manpowerRequest->headcount,
            $manpowerRequest->hired_count,
            $manpowerRequest->getAttribute('open_count'),
            (int) $manpowerRequest->getAttribute('advertised'),
            $manpowerRequest->needed_by ? format_date($manpowerRequest->needed_by).($manpowerRequest->needed_by->isPast() ? ' ('.__('Overdue').')' : '') : '—',
            $manpowerRequest->approved_at ? trans_choice(':count day|:count days', (int) $manpowerRequest->approved_at->diffInDays(now()), ['count' => (int) $manpowerRequest->approved_at->diffInDays(now())]) : '-',
        ])->all();

        return Excel::download(
            new ArrayExport([__('Request'), __('Designation'), __('Entity'), __('Type'), __('Wanted'), __('Hired'), __('Open'), __('Advertised'), __('Needed by'), __('Open for')], $rows),
            'open-positions-report-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['entity', 'timing', 'advertised', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<ManpowerRequest> */
    private function query(): Builder
    {
        return ManpowerRequest::query()
            ->approved()
            ->visibleTo(Auth::user())
            ->whereColumn('hired_count', '<', 'headcount')
            ->select('manpower_requests.*', DB::raw('headcount - hired_count as open_count'))
            ->withSum(['jobPostings as advertised' => fn ($q) => $q->whereIn('status', [JobPostingStatus::Draft, JobPostingStatus::Open])], 'openings')
            ->with(['designation', 'entity']);
    }

    /** @return array<int|string, string> choices of the "Entity" filter */
    private function entityOptions(): array
    {
        $units = app(EmployeeVisibility::class)->documentEntityOptionsFor(Auth::user());

        return $units;
    }

    /** @return array<int|string, string> choices of the "Needed by" filter */
    private function timingOptions(): array
    {
        return ['overdue' => __('Overdue'), 'month' => __('Within 30 days')];
    }

    /** @return array<int|string, string> choices of the "Advertised" filter */
    private function advertisedOptions(): array
    {
        return ['no' => __('Not advertised'), 'part' => __('Partly'), 'all' => __('Fully')];
    }

    /** @return Builder<ManpowerRequest> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if ($this->entity !== '' && array_key_exists($this->entity, $this->entityOptions())) {
            $query->where('entity_id', (int) $this->entity);
        }

        if ($this->timing !== '' && array_key_exists($this->timing, $this->timingOptions())) {
            $this->timing === 'overdue'
                    ? $query->where('needed_by', '<', today())
                    : $query->whereBetween('needed_by', [today(), today()->addDays(30)]);
        }

        if ($this->advertised !== '' && array_key_exists($this->advertised, $this->advertisedOptions())) {
            match ($this->advertised) {
                'no' => $query->having('advertised', '=', 0)->orHavingNull('advertised'),
                'part' => $query->having('advertised', '>', 0)->havingRaw('advertised < open_count'),
                default => $query->havingRaw('advertised >= open_count'),
            };
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('needed_by', 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('hrm::livewire.recruitment.reports.open-positions-report', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'entityOptions' => $this->entityOptions(),
            'timingOptions' => $this->timingOptions(),
            'advertisedOptions' => $this->advertisedOptions(),
        ]);
    }
}
