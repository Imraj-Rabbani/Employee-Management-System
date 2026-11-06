<?php

namespace App\Modules\Core\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Core\Models\FiscalYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Setup → Fiscal Years. [FND-04]
 * The name opens the Edit form; the Status column has "Make active".
 */
class FiscalYearsTable extends DataTable
{
    /** @return Builder<FiscalYear> */
    protected function query(): Builder
    {
        return FiscalYear::query();
    }

    /** Newest year first, until the user sorts by a column. */
    protected function defaultSort(): array
    {
        return ['start_date' => 'desc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()
                ->format(fn (FiscalYear $year) => new HtmlString(Blade::render(
                    '<button type="button" x-on:click="$dispatch(\'edit-fiscal-year\', { id: {{ $id }} })" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $name }}</button>',
                    ['id' => $year->id, 'name' => $year->name],
                ))),
            Column::make(__('Starts'), 'start_date')->sortable()
                ->format(fn (FiscalYear $year) => format_date($year->start_date)),
            Column::make(__('Ends'), 'end_date')->sortable()
                ->format(fn (FiscalYear $year) => format_date($year->end_date)),
            Column::make(__('Status'), 'is_active')
                ->format(fn (FiscalYear $year) => new HtmlString($year->is_active
                    ? Blade::render('<x-ui.badge color="success" dot>{{ __(\'Active\') }}</x-ui.badge>')
                    : Blade::render(
                        '<flux:button size="xs" variant="ghost" x-on:click="$dispatch(\'confirm\', {
                            title: @js(__(\'Make :name the active year?\', [\'name\' => $name])),
                            message: @js(__(\'Tax and reports will use this year from now on.\')),
                            confirmText: @js(__(\'Make active\')),
                            onConfirm: () => $wire.activate({{ $id }}),
                        })">{{ __(\'Make active\') }}</flux:button>',
                        ['id' => $year->id, 'name' => $year->name],
                    ))),
        ];
    }

    public function activate(int $id): void
    {
        $year = FiscalYear::findOrFail($id);
        $year->activate();

        $this->dispatch('toast', message: __(':name is now the active year.', ['name' => $year->name]), variant: 'success');
    }

    #[On('fiscal-year-saved')]
    public function refreshList(): void
    {
        // Re-renders the list.
    }
}
