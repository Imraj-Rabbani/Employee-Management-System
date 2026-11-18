<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\Applicant;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HRM → Applicants: the talent pool. Search by name, email or phone.
 * Phones are stored as digits only (01711000000), so search a phone without spaces or dashes.
 *
 * View: app/Modules/Hrm/resources/views/livewire/recruitment/applicant-table.blade.php
 */
class ApplicantTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['first_name', 'applications_count', 'created_at'];

    /** @var array<string, string> the form saved or deleted applicants: refresh and forget the ticked rows */
    protected $listeners = ['applicant-saved' => 'clearSelection'];

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
        Flux::modal('applicant-filters')->close();
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
        $this->reset('search');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('applicant-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Applicant $applicant) => [
            $applicant->name,
            $applicant->email,
            $applicant->phone,
            $applicant->current_employer,
            $applicant->applications_count,
            $applicant->cv_path ? __('Yes') : '',
            format_date($applicant->created_at),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Name'), __('Email'), __('Phone'), __('Current employer'), __('Applications'), __('CV'), __('Added')], $rows),
            'applicant-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Applicant> */
    private function query(): Builder
    {
        // withCount: one extra column "applications_count" in the same query, no query per row.
        return Applicant::query()->withCount('applications');
    }

    /** @return Builder<Applicant> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
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

        return view('hrm::livewire.recruitment.applicant-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
