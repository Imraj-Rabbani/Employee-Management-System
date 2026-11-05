<?php

namespace App\Modules\Core\Models;

use App\Core\Models\TenantModel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One fiscal year of the current company (tenant DB). [FND-04]
 *
 *     FiscalYear::active()                    the active year, or null
 *     FiscalYear::containing('2026-03-15')    the year that date falls in, or null
 *     $year->activate()                       make it THE active year (others are switched off)
 *
 * @property int $id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_active
 */
class FiscalYear extends TenantModel
{
    protected $fillable = ['name', 'start_date', 'end_date'];

    /** A new year is never active by itself: use activate(). */
    protected $attributes = ['is_active' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    public static function containing(CarbonInterface|string $date): ?self
    {
        $day = Carbon::parse($date)->toDateString();

        return static::whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)->first();
    }

    /**
     * Make this the only active year. Both updates run in one transaction,
     * so there is never a moment with zero or two active years.
     */
    public function activate(): void
    {
        DB::connection($this->getConnectionName())->transaction(function () {
            static::whereKeyNot($this->id)->where('is_active', true)->update(['is_active' => false]);

            $this->forceFill(['is_active' => true])->save();
        });
    }

    public function contains(CarbonInterface|string $date): bool
    {
        return Carbon::parse($date)->betweenIncluded($this->start_date->startOfDay(), $this->end_date->endOfDay());
    }

    /**
     * Years that overlap the period [$start, $end] (both days included), e.g. for validating a new year.
     *
     * @param  Builder<FiscalYear>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface|string $start, CarbonInterface|string $end): void
    {
        $query->whereDate('start_date', '<=', Carbon::parse($end)->toDateString())
            ->whereDate('end_date', '>=', Carbon::parse($start)->toDateString());
    }
}
