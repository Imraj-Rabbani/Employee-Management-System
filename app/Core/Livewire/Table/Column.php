<?php

namespace App\Core\Livewire\Table;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * One column of a DataTable.
 *
 *     Column::make(__('Name'), 'name')->sortable()->searchable()
 *     Column::make(__('Joined'), 'created_at')->format(fn (User $u) => format_date($u->created_at))
 *     Column::make(__('Name'), 'name')->opens('edit-branch')     the name is a link that opens the edit form
 *     Column::active()                                           Active / Inactive badge of is_active
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

    /**
     * The cell becomes a link-style button that sends the browser event $event with the row id,
     * e.g. opens('edit-branch') → $dispatch('edit-branch', { id: 5 }). The text is the cell's value.
     */
    public function opens(string $event): self
    {
        $text = $this->formatter ?? fn (Model $row) => data_get($row, $this->field);

        $this->formatter = fn (Model $row) => new HtmlString(Blade::render(
            '<button type="button" x-on:click="$dispatch(@js($event), { id: {{ $id }} })" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $text }}</button>',
            ['event' => $event, 'id' => $row->getKey(), 'text' => (string) $text($row)],
        ));

        return $this;
    }

    /** Active / Inactive badge for an is_active column. */
    public static function active(): self
    {
        return self::make(__('Active'), 'is_active')->sortable()
            ->format(fn (Model $row) => new HtmlString(Blade::render(
                '<x-ui.badge :color="$on ? \'success\' : \'neutral\'">{{ $on ? __(\'Active\') : __(\'Inactive\') }}</x-ui.badge>',
                ['on' => (bool) $row->getAttribute('is_active')],
            )));
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
