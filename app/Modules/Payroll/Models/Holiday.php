<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Payroll\Database\Factories\HolidayFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A public or festival holiday (legacy `holidays`). [TA-02]
 *
 * repeats_yearly: the same day and month every year from `date` on, e.g. 21 February (legacy: year = 0).
 * Holidays only count for employees whose holiday profile contains them.
 *
 * @property int $id
 * @property Carbon $date
 * @property string $title
 * @property bool $is_public
 * @property bool $is_festival
 * @property bool $repeats_yearly
 * @property string|null $detail
 */
#[UseFactory(HolidayFactory::class)]
class Holiday extends TenantModel
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected $fillable = ['date', 'title', 'is_public', 'is_festival', 'repeats_yearly', 'detail'];

    protected $attributes = ['is_public' => true, 'is_festival' => false, 'repeats_yearly' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_public' => 'boolean',
            'is_festival' => 'boolean',
            'repeats_yearly' => 'boolean',
        ];
    }

    /**
     * Holidays that fall on $date: Holiday::onDate($date)->get(). (Not "on": Model::on() picks a DB connection.)
     *
     * @param  Builder<self>  $query
     */
    public function scopeOnDate(Builder $query, CarbonInterface $date): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereDate('date', $date->toDateString())
            ->orWhere(fn (Builder $q) => $q
                ->where('repeats_yearly', true)
                ->whereMonth('date', $date->month)
                ->whereDay('date', $date->day)
                ->whereYear('date', '<=', $date->year)));
    }

    /**
     * Holidays in calendar year $year (repeating ones from earlier years included).
     *
     * @param  Builder<self>  $query
     */
    public function scopeInYear(Builder $query, int $year): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereYear('date', $year)
            ->orWhere(fn (Builder $q) => $q->where('repeats_yearly', true)->whereYear('date', '<=', $year)));
    }

    /** Same rule as scopeOnDate(), for a holiday already loaded (no query). */
    public function fallsOn(CarbonInterface $date): bool
    {
        return $this->date->isSameDay($date)
            || ($this->repeats_yearly && $this->date->format('m-d') === $date->format('m-d') && $this->date->year <= $date->year);
    }

    /**
     * The days this holiday falls on that may already be processed: its date, or for a yearly one its day
     * this year and last year (older years are normally locked by payroll). Only days up to $today.
     *
     * @return list<string> YYYY-MM-DD
     */
    public function pastDates(string $today): array
    {
        if (! $this->repeats_yearly) {
            return $this->date->toDateString() <= $today ? [$this->date->toDateString()] : [];
        }

        $dates = [];
        $thisYear = (int) substr($today, 0, 4);
        foreach ([$thisYear - 1, $thisYear] as $year) {
            // 29 February only exists in leap years: checkdate() skips it in the others.
            if ($year >= $this->date->year && checkdate($this->date->month, $this->date->day, $year)) {
                $date = sprintf('%04d-%02d-%02d', $year, $this->date->month, $this->date->day);
                if ($date <= $today) {
                    $dates[] = $date;
                }
            }
        }

        return $dates;
    }

    /** @return BelongsToMany<HolidayProfile, $this> */
    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(HolidayProfile::class);
    }
}
