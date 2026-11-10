<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Database\Factories\AttendanceDayFactory;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\DayType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One employee's processed day (legacy `attendance`). Written by attendance processing only. [TA-05]
 * All durations are minutes.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property int|null $shift_id
 * @property DayType $day_type
 * @property AttendanceStatus $status
 * @property Carbon|null $in_at
 * @property Carbon|null $out_at
 * @property int $punch_count
 * @property int $worked_minutes
 * @property int $late_minutes
 * @property bool $is_late
 * @property bool $used_grace
 * @property int $early_exit_minutes
 * @property int $ot_pre_minutes
 * @property int $ot_post_minutes
 * @property int $ot_minutes
 * @property int|null $ot_approved_minutes
 * @property string|null $note
 * @property bool $is_locked
 * @property Carbon|null $processed_at
 */
#[UseFactory(AttendanceDayFactory::class)]
class AttendanceDay extends TenantModel
{
    /** @use HasFactory<AttendanceDayFactory> */
    use HasFactory;

    /**
     * No activity-log entry per day: every employee's days are rewritten nightly and on every change.
     * Who changed attendance is in the corrections (7.6), not here.
     *
     * @var list<string>
     */
    protected static $recordEvents = [];

    protected $fillable = [
        'employee_id', 'date', 'shift_id', 'day_type', 'status', 'in_at', 'out_at', 'punch_count',
        'worked_minutes', 'late_minutes', 'is_late', 'used_grace', 'early_exit_minutes',
        'ot_pre_minutes', 'ot_post_minutes', 'ot_minutes', 'note', 'processed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'day_type' => DayType::class,
            'status' => AttendanceStatus::class,
            'in_at' => 'datetime',
            'out_at' => 'datetime',
            'is_late' => 'boolean',
            'used_grace' => 'boolean',
            'is_locked' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    /** "8:45" for 525 minutes: how times are shown everywhere. */
    public static function hoursLabel(int $minutes): string
    {
        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
