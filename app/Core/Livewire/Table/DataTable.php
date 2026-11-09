<?php

namespace App\Core\Livewire\Table;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Base list screen (replaces the Crudomator list). A child class only declares:
 *
 *     query()        which rows (Eloquent query)
 *     columns()      which columns (Column::make(...))
 *     filters()      optional dropdown filters (SelectFilter::make(...))
 *     bulkActions()  optional actions for selected rows, each handled by a bulk{Key}(array $ids) method
 *     defaultSort()  optional order before the user sorts, e.g. ['start_date' => 'desc']
 *
 * Search, sorting, filters, pagination, column toggle, selection and Excel export come from here.
 * Search, sort, filters and page size are kept in the URL, so a filtered list can be bookmarked or shared.
 */
abstract class DataTable extends Component
{
    use WithPagination;

    /** Allowed page sizes. Anything else (e.g. typed into the URL) falls back to the first. */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** @var array<string, string> filter key => chosen value */
    #[Url(except: [])]
    public array $filters = [];

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** @var list<string> fields of the columns the user has hidden (remembered in the session) */
    #[Session]
    public array $hiddenColumns = [];

    /** @var list<string> ids of the selected rows */
    public array $selected = [];

    public bool $selectPage = false;

    /** @return Builder<covariant Model> */
    abstract protected function query(): Builder;

    /** @return list<Column> */
    abstract protected function columns(): array;

    /**
     * Order used until the user clicks a column header, e.g. ['start_date' => 'desc'].
     *
     * @return array<string, 'asc'|'desc'>
     */
    protected function defaultSort(): array
    {
        return [];
    }

    /** @return list<SelectFilter> */
    protected function filters(): array
    {
        return [];
    }

    /**
     * For SelectFilter options with number keys (ids): filter values arrive from the browser / URL as
     * strings, so the option keys must be strings too. (Moved here from EmployeeTable in 6.9.)
     *
     * @param  array<int|string, string>  $options
     * @return array<string, string>
     */
    protected function stringKeys(array $options): array
    {
        $out = [];
        foreach ($options as $key => $label) {
            $out[(string) $key] = $label;
        }

        return $out;
    }

    /**
     * Key => settings. Key "delete" is handled by a bulkDelete(array $ids) method.
     *
     * @return array<string, array{label: string, confirm?: string, variant?: string}>
     */
    protected function bulkActions(): array
    {
        return [];
    }

    protected function exportFilename(): string
    {
        return Str::kebab(class_basename($this)).'-'.now()->format('Y-m-d').'.xlsx';
    }

    // ----------------------------------------------------------------- user actions

    public function sort(string $field): void
    {
        if (! in_array($field, $this->sortableFields(), true)) {
            return;
        }

        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function toggleColumn(string $field): void
    {
        $toggleable = array_map(fn (Column $c) => $c->field, array_filter($this->columns(), fn (Column $c) => $c->toggleable));

        if (! in_array($field, $toggleable, true)) {
            return;
        }

        $this->hiddenColumns = in_array($field, $this->hiddenColumns, true)
            ? array_values(array_diff($this->hiddenColumns, [$field]))
            : [...$this->hiddenColumns, $field];
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'filters');
        $this->resetPage();
        $this->clearSelection();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectPage = false;
    }

    public function runBulkAction(string $key): void
    {
        $method = 'bulk'.Str::studly($key);

        // Only actions declared in bulkActions() can be called from the browser.
        abort_unless(array_key_exists($key, $this->bulkActions()) && method_exists($this, $method), 403);

        $this->{$method}($this->selected);
        $this->clearSelection();
    }

    public function export(): BinaryFileResponse
    {
        return Excel::download(new TableExport($this->filteredQuery(), $this->visibleColumns()), $this->exportFilename());
    }

    // ----------------------------------------------------------------- Livewire hooks

    /** Any change to search, filters or page size starts again at page 1 with nothing selected. */
    public function updated(string $property): void
    {
        if ($property === 'search' || $property === 'perPage' || str_starts_with($property, 'filters')) {
            $this->resetPage();
            $this->clearSelection();
        }
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selected = $value
            ? $this->rows()->getCollection()->map(fn (Model $row) => (string) $row->getKey())->values()->all()
            : [];
    }

    public function updatedPaginators(): void
    {
        $this->selectPage = false;
    }

    // ----------------------------------------------------------------- query building

    /** @return Builder<covariant Model> */
    protected function filteredQuery(): Builder
    {
        $query = $this->query();

        if (($term = trim($this->search)) !== '') {
            // Escape LIKE wildcards so a search for "50%" means the text "50%".
            $like = '%'.addcslashes($term, '%_\\').'%';
            $fields = array_merge(...array_map(fn (Column $c) => $c->searchFields, $this->columns()));

            $query->where(function (Builder $q) use ($fields, $like) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', $like);
                }
            });
        }

        foreach ($this->filters() as $filter) {
            $value = (string) ($this->filters[$filter->key] ?? '');

            if ($value !== '') {
                $filter->apply($query, $value);
            }
        }

        if (in_array($this->sortBy, $this->sortableFields(), true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            foreach ($this->defaultSort() as $field => $direction) {
                $query->orderBy($field, $direction);
            }
        }

        return $query;
    }

    /** @return LengthAwarePaginator<int, Model> */
    protected function rows(): LengthAwarePaginator
    {
        $perPage = in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : self::PER_PAGE_OPTIONS[0];

        return $this->filteredQuery()->paginate($perPage);
    }

    /** @return list<string> */
    protected function sortableFields(): array
    {
        return array_values(array_map(fn (Column $c) => $c->field, array_filter($this->columns(), fn (Column $c) => $c->sortable)));
    }

    /** @return list<Column> */
    protected function visibleColumns(): array
    {
        return array_values(array_filter(
            $this->columns(),
            fn (Column $c) => ! $c->toggleable || ! in_array($c->field, $this->hiddenColumns, true),
        ));
    }

    public function render(): View
    {
        return view('core.table', [
            'rows' => $this->rows(),
            'columns' => $this->visibleColumns(),
            'allColumns' => $this->columns(),
            'filterList' => $this->filters(),
            'bulkActionList' => $this->bulkActions(),
            // No searchable column (e.g. values computed in PHP) → no search box.
            'hasSearch' => array_filter($this->columns(), fn (Column $c) => $c->searchable) !== [],
            'hasActiveFilters' => trim($this->search) !== '' || array_filter($this->filters) !== [],
        ]);
    }
}
