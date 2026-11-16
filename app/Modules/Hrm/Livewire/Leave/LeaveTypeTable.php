<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\LeaveType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** List of leave types, in their order. Click a name to edit it. */
class LeaveTypeTable extends Component
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
    private const SORTABLE = ['name', 'code', 'earn_type', 'profile_items_count', 'sequence', 'is_active'];

    /** @var array<string, string> profile counts change when a profile is saved */
    protected $listeners = ['leave-type-saved' => '$refresh', 'leave-profile-saved' => '$refresh'];

    /** "1–3 days", "up to 10 days", "at least 2 days" or "—". */
    public function range(LeaveType $type): string
    {
        $min = $type->min_days;
        $max = $type->max_days;

        return match (true) {
            $min !== null && $max !== null => __(':min–:max days', ['min' => $min, 'max' => $max]),
            $max !== null => __('up to :max days', ['max' => $max]),
            $min !== null => __('at least :min days', ['min' => $min]),
            default => '—',
        };
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
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (LeaveType $leaveType) => [
            $leaveType->name,
            $leaveType->code,
            collect([$leaveType->gender?->label(), $leaveType->religion?->label()])
                ->filter()->implode(' · ') ?: __('Everyone'),
            $this->range($leaveType),
            $leaveType->earn_type->label(),
            implode(', ', array_filter([
                $leaveType->is_lwp ? __('Without pay') : null,
                $leaveType->allow_half_day ? __('Half day') : null,
                $leaveType->can_carry_forward ? __('Carry forward') : null,
                $leaveType->is_encashable ? __('Encashable') : null,
                $leaveType->hr_only ? __('HR only') : null,
            ])),
            $leaveType->profile_items_count,
            $leaveType->sequence,
            $leaveType->is_active ? __('Active') : __('Inactive'),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Name'), __('Code'), __('For'), __('Per application'), __('Earned'), __('Rules'), __('Profiles'), __('Order'), __('Active')], $rows),
            'leave-type-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<LeaveType> */
    private function query(): Builder
    {
        return LeaveType::query()->withCount('profileItems');
    }

    /** @return Builder<LeaveType> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('sequence', 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('hrm::livewire.leave.leave-type-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
