<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Exports\ArrayExport;
use App\Modules\Payroll\Models\Holiday;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** List of holidays, newest first, with a Year filter. Click a title to edit it. */
class HolidayTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Year": '' = all */
    #[Url(as: 'year', except: '')]
    public string $year = '';

    /** Filter "Type": '' = all */
    #[Url(as: 'type', except: '')]
    public string $type = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['date', 'title'];

    /** @var array<string, string> */
    protected $listeners = ['holiday-saved' => '$refresh'];

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
        $this->reset('search', 'year', 'type');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Holiday $holiday) => [
            $holiday->repeats_yearly
                ? $holiday->date->translatedFormat('j F').' · '.__('every year')
                : format_date($holiday->date).' · '.$holiday->date->translatedFormat('l'),
            $holiday->title,
            trim(($holiday->is_public ? __('Public') : '').' '.($holiday->is_festival ? __('Festival') : '')),
            $holiday->profiles->pluck('name')->join(', ') ?: '—',
        ])->all();

        return Excel::download(
            new ArrayExport([__('Date'), __('Title'), __('Type'), __('Calendars')], $rows),
            'holiday-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'year', 'type', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Holiday> */
    private function query(): Builder
    {
        return Holiday::query()->with('profiles:id,name');
    }

    /** @return array<int|string, string> choices of the "Year" filter */
    private function yearOptions(): array
    {
        // Years that have holidays, plus this year and next (so planning next year starts with an empty list).
        $years = Holiday::query()->selectRaw('YEAR(date) as y')->distinct()->pluck('y')
            ->push(now()->year, now()->year + 1)->unique()->sortDesc()
            ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])->all();

        return $years;
    }

    /** @return array<int|string, string> choices of the "Type" filter */
    private function typeOptions(): array
    {
        return ['public' => __('Public'), 'festival' => __('Festival')];
    }

    /** @return Builder<Holiday> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $like));
        }

        if ($this->year !== '' && array_key_exists($this->year, $this->yearOptions())) {
            $query->whereIn('id', Holiday::query()->inYear((int) $this->year)->select('id'));
        }

        if ($this->type !== '' && array_key_exists($this->type, $this->typeOptions())) {
            $query->where($this->type === 'public' ? 'is_public' : 'is_festival', true);
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('date', 'desc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('payroll::livewire.attendance.holiday-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'yearOptions' => $this->yearOptions(),
            'typeOptions' => $this->typeOptions(),
        ]);
    }
}
