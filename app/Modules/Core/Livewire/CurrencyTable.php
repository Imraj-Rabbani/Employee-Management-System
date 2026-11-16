<?php

namespace App\Modules\Core\Livewire;

use App\Core\Exports\ArrayExport;
use App\Modules\Core\Models\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Setup → Currencies. Click a row's code to edit it.
 * View: app/Modules/Core/resources/views/livewire/currency-table.blade.php
 */
class CurrencyTable extends Component
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

    private const SORTABLE = ['code', 'name', 'is_active'];

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Currency $currency) => [
            $currency->code,
            $currency->name,
            $currency->symbol,
            $currency->exchange_rate,
            $currency->is_active ? __('Active') : __('Inactive'),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Code'), __('Name'), __('Symbol'), __('Exchange rate'), __('Active')], $rows),
            'currency-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** The form saved a currency: re-render the list. */
    #[On('currency-saved')]
    public function refreshList(): void {}

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Currency> */
    private function filteredQuery(): Builder
    {
        $query = Currency::query();

        if (trim($this->search) !== '') {
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like));
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('code');
        }

        return $query;
    }

    public function render(): View
    {
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('core::livewire.currency-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
