<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Models\AttendanceDay;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll → Attendance → Daily attendance: the processed days (attendance_days), newest first.
 * Only employees the user can see. Read-only: a wrong day is fixed by a correction or by processing again.
 *
 * View: app/Modules/Payroll/resources/views/livewire/attendance/attendance-day-table.blade.php
 */
class AttendanceDayTable extends Component
{
    use WithPagination;

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Month": '' = all */
    #[Url(as: 'month', except: '')]
    public string $month = '';

    /** Filter "Status": '' = all */
    #[Url(as: 'status', except: '')]
    public string $status = '';

    /** Filter "Entity": '' = all */
    #[Url(as: 'entity', except: '')]
    public string $entity = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['date', 'status', 'worked_minutes', 'late_minutes', 'ot_minutes'];

    /** @var array<string, string> */
    protected $listeners = ['attendance-days-saved' => '$refresh'];

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
        $this->reset('month', 'status', 'entity');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (AttendanceDay $attendanceDay) => [
            format_date($attendanceDay->date).' · '.$attendanceDay->date->translatedFormat('D'),
            $attendanceDay->employee ? $attendanceDay->employee->code.' · '.$attendanceDay->employee->name : '—',
            $attendanceDay->shift->code ?? '—',
            implode(', ', array_filter([$attendanceDay->status->label(), $attendanceDay->is_late ? __('Late') : null, $attendanceDay->is_corrected ? __('Corrected') : null, $attendanceDay->is_locked ? __('Locked') : null])),
            ($attendanceDay->in_at === null ? '—' : $attendanceDay->in_at->format('H:i')),
            ($attendanceDay->out_at === null ? '—' : $attendanceDay->out_at->format('H:i')),
            ($attendanceDay->worked_minutes === 0 ? '—' : AttendanceDay::hoursLabel($attendanceDay->worked_minutes)),
            ($attendanceDay->late_minutes === 0 ? '—' : AttendanceDay::hoursLabel($attendanceDay->late_minutes)),
            ($attendanceDay->early_exit_minutes === 0 ? '—' : AttendanceDay::hoursLabel($attendanceDay->early_exit_minutes)),
            ($attendanceDay->ot_minutes === 0 ? '—' : AttendanceDay::hoursLabel($attendanceDay->ot_minutes)),
            $attendanceDay->note ?? '',
            $attendanceDay->processed_at ? format_datetime($attendanceDay->processed_at) : '—',
        ])->all();

        return Excel::download(
            new ArrayExport([__('Date'), __('Employee'), __('Shift'), __('Status'), __('In'), __('Out'), __('Worked'), __('Late'), __('Early exit'), __('Overtime'), __('Note'), __('Processed')], $rows),
            'attendance-day-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['month', 'status', 'entity', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<AttendanceDay> */
    private function query(): Builder
    {
        // whereHas('employee') goes through Employee's visibility scope: only employees this user may see.
        return AttendanceDay::query()
            ->with(['employee:id,code,first_name,last_name', 'shift:id,code'])
            ->whereHas('employee');
    }

    /** @return array<int|string, string> choices of the "Month" filter */
    private function monthOptions(): array
    {
        $months = [];

        for ($m = now()->startOfMonth(), $i = 0; $i < 13; $i++, $m = $m->subMonth()) {
            $months[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }

        return $months;
    }

    /** @return array<int|string, string> choices of the "Status" filter */
    private function statusOptions(): array
    {
        return AttendanceStatus::options();
    }

    /** @return array<int|string, string> choices of the "Entity" filter */
    private function entityOptions(): array
    {
        return app(EmployeeVisibility::class)->entityOptionsFor(Auth::user());
    }

    /** @return Builder<AttendanceDay> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if ($this->month !== '' && array_key_exists($this->month, $this->monthOptions())) {
            $start = Carbon::createFromFormat('!Y-m', $this->month);
            $query->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
        }

        if ($this->status !== '' && array_key_exists($this->status, $this->statusOptions())) {
            $query->where('status', $this->status);
        }

        if ($this->entity !== '' && array_key_exists($this->entity, $this->entityOptions())) {
            $query->whereHas('employee', fn (Builder $e) => $e->inEntities([(int) $this->entity]));
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('date', 'desc');
            $query->orderBy('employee_id', 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('payroll::livewire.attendance.attendance-day-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'monthOptions' => $this->monthOptions(),
            'statusOptions' => $this->statusOptions(),
            'entityOptions' => $this->entityOptions(),
        ]);
    }
}
