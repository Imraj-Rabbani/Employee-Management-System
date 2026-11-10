<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Database\Factories\PunchLogFactory;
use App\Modules\Payroll\Enums\PunchSource;
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
     */
    public static function linkEmployees(): int
    {
        $codes = Employee::withoutGlobalScope(VisibleToCurrentUser::class)
            ->whereNotNull('access_code')->where('access_code', '<>', '')
            ->get(['id', 'access_code'])
            ->groupBy('access_code')
            ->filter(fn ($group) => $group->count() === 1)
            ->map(fn ($group) => $group->first()->id);

        $linked = 0;
        foreach ($codes as $code => $employeeId) {
            $linked += static::query()->whereNull('employee_id')->where('access_code', (string) $code)
                ->update(['employee_id' => $employeeId, 'updated_at' => DB::raw('updated_at')]);
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
