<?php

namespace App\Core\Livewire\Table;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dropdown filter above a DataTable.
 *
 *     SelectFilter::make('status', __('Status'), ['active' => __('Active'), 'left' => __('Left')])
 *     SelectFilter::make('verified', __('Verified'), [...])->query(fn (Builder $q, string $value) => ...)
 *
 * Without ->query() the filter does where($key, $value).
 */
class SelectFilter
{
    private ?Closure $callback = null;

    /** @param  array<string, string>  $options  value => label */
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $options,
    ) {}

    /** @param  array<string, string>  $options  value => label */
    public static function make(string $key, string $label, array $options): self
    {
        return new self($key, $label, $options);
    }

    public function query(Closure $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    /** @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query */
    public function apply(Builder $query, string $value): void
    {
        // Ignore values that are not one of the options (e.g. edited in the URL).
        if (! array_key_exists($value, $this->options)) {
            return;
        }

        if ($this->callback) {
            ($this->callback)($query, $value);
        } else {
            $query->where($this->key, $value);
        }
    }
}
