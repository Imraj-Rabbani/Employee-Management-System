<?php

namespace App\Core\Livewire\Table;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Excel export of a DataTable: same search, filters, sort and visible columns as on screen, all pages.
 *
 * @implements WithMapping<Model>
 */
class TableExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<covariant Model>  $query
     * @param  list<Column>  $columns
     */
    public function __construct(
        private Builder $query,
        private array $columns,
    ) {}

    /** @return Builder<covariant Model> */
    public function query(): Builder
    {
        return $this->query;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return array_map(fn (Column $column) => $column->label, $this->columns);
    }

    /**
     * @param  Model  $row
     * @return list<string>
     */
    public function map($row): array
    {
        return array_map(fn (Column $column) => $column->exportValue($row), $this->columns);
    }
}
