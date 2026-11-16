<?php

namespace App\Modules\Core\Livewire;

use App\Core\Exports\ArrayExport;
use App\Modules\Core\Models\FiscalYear;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Setup → Fiscal Years.
 * The name opens the Edit form; the Status column has "Make active".
 * View: app/Modules/Core/resources/views/livewire/fiscal-years-table.blade.php
 */
class FiscalYearsTable extends Component
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

    private const SORTABLE = ['name', 'start_date', 'end_date'];

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function activate(int $id): void
    {
        $year = FiscalYear::findOrFail($id);
        $year->activate();

        $this->dispatch('toast', message: __(':name is now the active year.', ['name' => $year->name]), variant: 'success');
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (FiscalYear $year) => [
            $year->name,
            format_date($year->start_date),
            format_date($year->end_date),
            $year->is_active ? __('Active') : '',
        ])->all();

        return Excel::download(
            new ArrayExport([__('Name'), __('Starts'), __('Ends'), __('Status')], $rows),
            'fiscal-years-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** The form saved a year: re-render the list. */
    #[On('fiscal-year-saved')]
    public function refreshList(): void {}

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<FiscalYear> */
    private function filteredQuery(): Builder
    {
        $query = FiscalYear::query();

        if (trim($this->search) !== '') {
            $query->where('name', 'like', '%'.addcslashes(trim($this->search), '%_\\').'%');
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('start_date', 'desc'); // newest year first until the user sorts
        }

        return $query;
    }

    public function render(): View
    {
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('core::livewire.fiscal-years-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
