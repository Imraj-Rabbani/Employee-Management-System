<?php

namespace App\Core\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * For "effective-dated" tables: each row is valid from `effective_from` to `effective_to` (both days
 * included); `effective_to` NULL = still valid. Changing something never overwrites the old row: the old
 * row is CLOSED and a new one starts, so the full history stays. (Architecture §9.3)
 *
 *     class EmployeeOrgAssignment extends TenantModel { use HasEffectiveDates; }
 *
 *     $employee->orgAssignments()->effectiveOn('2026-03-01')->first()   the row valid on that day
 *     $employee->orgAssignments()->current()->first()                    the row valid today
 *     $row->close($newStart->copy()->subDay())                           ends it the day before the next one
 *     $row->isOpen()                                                     effective_to is NULL
 *
 * A TRAIT = methods copied into the class that "uses" it. initializeHasEffectiveDates() is called by
 * Eloquent for every new model instance (convention: initialize + trait name), so the casts are added
 * without the model having to repeat them.
 *
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
trait HasEffectiveDates
{
    public function initializeHasEffectiveDates(): void
    {
        $this->mergeCasts(['effective_from' => 'date', 'effective_to' => 'date']);
    }

    /**
     * Rows valid on $date: started on or before it, and not ended before it.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEffectiveOn(Builder $query, DateTimeInterface|string $date): void
    {
        $day = Carbon::parse($date)->toDateString();

        $query->where($this->qualifyColumn('effective_from'), '<=', $day)
            ->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('effective_to'))
                ->orWhere($this->qualifyColumn('effective_to'), '>=', $day));
    }

    /**
     * Rows valid today.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $this->scopeEffectiveOn($query, today());
    }

    /**
     * Newest first (the usual order for a history list).
     *
     * @param  Builder<static>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('effective_from'))->orderByDesc($this->getQualifiedKeyName());
    }

    /** End this row on $lastDay (inclusive). Usually the day before the next row starts. */
    public function close(DateTimeInterface|string $lastDay): void
    {
        $this->update(['effective_to' => Carbon::parse($lastDay)->toDateString()]);
    }

    public function isOpen(): bool
    {
        return $this->effective_to === null;
    }
}
