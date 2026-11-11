<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Database\Factories\PunchLogFactory;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Services\AttendanceReprocessor;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One punch (clock in or out) from any source (legacy `punchlog`). Raw data: never edited. [TA-04]
 * Whether a punch is "in" or "out" is not stored: attendance processing (7.4) takes the first and last
 * punch of the working day.
 *
 * @property int $id
 * @property int|null $employee_id
 * @property string|null $access_code
 * @property Carbon $punched_at
 * @property PunchSource $source
 * @property int|null $device_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $distance_m
 * @property string|null $batch
 * @property int|null $created_by_user_id
 */
#[UseFactory(PunchLogFactory::class)]
class PunchLog extends TenantModel
{
    /** @use HasFactory<PunchLogFactory> */
    use HasFactory;

    /**
     * No activity-log entry per punch: devices add thousands a day, and a punch is never changed.
     * (Spatie's LogsActivity records only the events listed here.)
     *
     * @var list<string>
     */
    protected static $recordEvents = [];

    protected $fillable = [
        'employee_id', 'access_code', 'punched_at', 'source', 'device_id',
        'latitude', 'longitude', 'distance_m', 'batch', 'created_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'source' => PunchSource::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Punches of one employee between two moments, in time order: PunchLog::forEmployeeBetween($e, $from, $to).
     * An overnight shift's day runs past midnight, so 7.4 asks for a time window, not a date.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForEmployeeBetween(Builder $query, Employee|int $employee, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('employee_id', $employee instanceof Employee ? $employee->id : $employee)
            ->whereBetween('punched_at', [$from, $to])
            ->orderBy('punched_at');
    }

    /**
     * Fills employee_id on punches that have none yet, by access code (employees.access_code).
     * Run after a sync / upload, and after HR gives someone an Attendance ID. Codes used by two
     * employees are skipped (ambiguous). Returns how many punches were linked.
     *
     * Every new punch passes through here (StorePunches stores them without employee, then links), so this is
     * also where attendance is processed again (7.5): for each linked employee, the days of those punches plus
     * the day before (an overnight shift's punch the next morning belongs to the day before), up to YESTERDAY.
     * Today is left to the nightly run: punches of a running day would only give "absent" or "single punch".
     * So a punch this morning re-processes yesterday: that is what gives a night shift its 06:00 "out", which
     * arrives after the 01:00 nightly run.
     */
    public static function linkEmployees(): int
    {
        // Codes that have unmatched punches, with the first and last such punch.
        $waiting = static::query()->whereNull('employee_id')->whereNotNull('access_code')->where('access_code', '<>', '')
            ->groupBy('access_code')
            ->get([DB::raw('access_code'), DB::raw('MIN(punched_at) as first_at'), DB::raw('MAX(punched_at) as last_at')]);
        if ($waiting->isEmpty()) {
            return 0;
        }

        $codes = Employee::withoutGlobalScope(VisibleToCurrentUser::class)
            ->whereIn('access_code', $waiting->pluck('access_code'))
            ->get(['id', 'access_code'])
            ->groupBy('access_code')
            ->filter(fn ($group) => $group->count() === 1)
            ->map(fn ($group) => $group->first()->id);

        $reprocessor = app(AttendanceReprocessor::class);
        $yesterday = Carbon::parse($reprocessor->today())->subDay()->toDateString();
        $linked = 0;
        foreach ($waiting as $row) {
            $employeeId = $codes[$row->access_code] ?? null;
            if ($employeeId === null) {
                continue;
            }
            $linked += static::query()->whereNull('employee_id')->where('access_code', (string) $row->access_code)
                ->update(['employee_id' => $employeeId, 'updated_at' => DB::raw('updated_at')]);

            $from = Carbon::parse($row->getAttribute('first_at'))->subDay()->toDateString();
            $to = min(Carbon::parse($row->getAttribute('last_at'))->toDateString(), $yesterday);
            $reprocessor->queue([$employeeId], $from, $to);
        }

        return $linked;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
