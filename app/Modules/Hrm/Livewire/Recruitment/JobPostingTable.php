<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HRM → Job Postings: the list.
 * Only postings of org units the user can see (JobPosting::visibleTo). Click the number to open its page.
 *
 * View: app/Modules/Hrm/resources/views/livewire/recruitment/job-posting-table.blade.php
 */
class JobPostingTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Status": '' = all */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['id', 'title', 'openings', 'open_date', 'close_date', 'status'];

    /** @var array<string, string> refresh the list when the form saved something */
    protected $listeners = ['job-posting-saved' => '$refresh'];

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
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (JobPosting $jobPosting) => [
            $jobPosting->number(),
            $jobPosting->title,
            $jobPosting->manpowerRequest->orgUnit->name ?? '-',
            $jobPosting->manpowerRequest->number(),
            $jobPosting->openings,
            format_date($jobPosting->open_date),
            $jobPosting->close_date ? format_date($jobPosting->close_date) : __('Until closed'),
            $jobPosting->status->label(),
        ])->all();

        return Excel::download(
            new ArrayExport([__('No.'), __('Title'), __('Org unit'), __('Request'), __('Openings'), __('Opens'), __('Closes'), __('Status')], $rows),
            'job-posting-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<JobPosting> */
    private function query(): Builder
    {
        // "manpowerRequest.orgUnit": nested eager loading, the request AND its org unit in 2 queries.
        return JobPosting::query()
            ->visibleTo(Auth::user())
            ->with(['manpowerRequest.orgUnit']);
    }

    /** @return array<int|string, string> choices of the "Status" filter */
    private function statusOptions(): array
    {
        return JobPostingStatus::options();
    }

    /** @return Builder<JobPosting> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $like));
        }

        if ($this->status !== '' && array_key_exists($this->status, $this->statusOptions())) {
            $query->where('status', $this->status);
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

        return view('hrm::livewire.recruitment.job-posting-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'statusOptions' => $this->statusOptions(),
        ]);
    }
}
