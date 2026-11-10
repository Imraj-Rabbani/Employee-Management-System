<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Database\Factories\RosterItemFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One cell of a roster: what $employee is planned to do on $date. [TA-03]
 *
 *   shift_id set           works that shift (start_time / end_time may change the hours for this day)
 *   is_off                 day off (shift_id is NULL)
 *   is_on_call             on call: not expected in, but may be called
 *
 * @property int $id
 * @property int $roster_id
 * @property int $employee_id
 * @property Carbon $date
 * @property int|null $shift_id
 * @property bool $is_off
 * @property bool $is_on_call
 * @property string|null $start_time
 * @property string|null $end_time
 */
#[UseFactory(RosterItemFactory::class)]
class RosterItem extends TenantModel
{
    /** @use HasFactory<RosterItemFactory> */
    use HasFactory;

    protected $fillable = ['roster_id', 'employee_id', 'date', 'shift_id', 'is_off', 'is_on_call', 'start_time', 'end_time'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date', 'is_off' => 'boolean', 'is_on_call' => 'boolean'];
    }

    /**
     * The plan that counts for $employee on $date: the cell of the most recently APPROVED roster, or null
     * (then the employee's own / default shift applies). Used by attendance processing (7.4).
     */
    public static function planFor(Employee|int $employee, CarbonInterface $date): ?self
    {
        return static::query()
            ->join('rosters', 'rosters.id', '=', 'roster_items.roster_id')
            ->whereNotNull('rosters.approved_at')
            ->where('roster_items.employee_id', $employee instanceof Employee ? $employee->id : $employee)
            ->whereDate('roster_items.date', $date->toDateString())
            ->orderByDesc('rosters.approved_at')->orderByDesc('rosters.id')
            ->select('roster_items.*')
            ->first();
    }

    /** The cell as text, as in Roster::cellMatrix(): the shift id, "off", "call" or "". */
    public function cellValue(): string
    {
        return match (true) {
            $this->is_off => 'off',
            $this->shift_id !== null => (string) $this->shift_id,
            $this->is_on_call => 'call',
            default => '',
        };
    }

    /** @return BelongsTo<Roster, $this> */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
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
