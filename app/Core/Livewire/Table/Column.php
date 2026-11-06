<?php

namespace App\Core\Livewire\Table;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * One column of a DataTable.
 *
 *     Column::make(__('Name'), 'name')->sortable()->searchable()
 *     Column::make(__('Joined'), 'created_at')->format(fn (User $u) => format_date($u->created_at))
 */
class Column
{
    public bool $sortable = false;

    public bool $searchable = false;

    public bool $toggleable = true;

    public bool $alignRight = false;

    private ?Closure $formatter = null;

    private function __construct(
        public readonly string $label,
        public readonly string $field,
    ) {}

    /** $field is the database column used for sorting/searching and the default cell value. */
    public static function make(string $label, string $field): self
    {
        return new self($label, $field);
    }

    public function sortable(): self
    {
        $this->sortable = true;

        return $this;
    }

    public function searchable(): self
    {
        $this->searchable = true;

        return $this;
    }

    /** The column is always visible and is not listed in the "Columns" menu. */
    public function alwaysVisible(): self
    {
        $this->toggleable = false;

        return $this;
    }

    public function alignRight(): self
    {
        $this->alignRight = true;

        return $this;
    }

    /**
     * Custom cell content. Return a string (escaped) or an HtmlString/Htmlable (printed as HTML).
     */
    public function format(Closure $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function value(Model $row): mixed
    {
        return $this->formatter ? ($this->formatter)($row) : data_get($row, $this->field);
    }

    /** Plain text for Excel: HTML (badges etc.) is reduced to its text. */
    public function exportValue(Model $row): string
    {
        $value = $this->value($row);

        if ($value instanceof Htmlable) {
            return trim(preg_replace('/\s+/', ' ', strip_tags($value->toHtml())) ?? '');
        }

        return (string) $value;
    }
}
