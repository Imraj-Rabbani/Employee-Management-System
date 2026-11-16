<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\InterviewFactor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** List of interview factors. Click a row's name to edit it. */
class InterviewFactorTable extends Component
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
    private const SORTABLE = ['sequence', 'name', 'weight', 'is_required', 'is_active'];

    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['interview-factor-saved' => '$refresh'];

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
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (InterviewFactor $interviewFactor) => [
            $interviewFactor->sequence,
            $interviewFactor->name,
            rtrim(rtrim((string) $interviewFactor->getAttribute('weight'), '0'), '.').'%',
            $interviewFactor->getAttribute('is_required') ? __('Yes') : '',
            $interviewFactor->description,
            $interviewFactor->is_active ? __('Active') : __('Inactive'),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Order'), __('Name'), __('Weight'), __('Required'), __('Description'), __('Active')], $rows),
            'interview-factor-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<InterviewFactor> */
    private function query(): Builder
    {
        return InterviewFactor::query();
    }

    /** @return Builder<InterviewFactor> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('sequence', 'asc');
            $query->orderBy('name', 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('hrm::livewire.recruitment.interview-factor-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
