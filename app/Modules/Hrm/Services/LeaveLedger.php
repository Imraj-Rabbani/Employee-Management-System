<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Models\LeaveLedgerEntry;
use App\Modules\Hrm\Models\LeaveType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The ONE place that changes leave balances. [LV-03] Architecture §11.
 *
 * Every change is a new leave_ledger row (never an edit), and after each row the cached leave_balances
 * row is rebuilt from the ledger, in the same transaction. So a balance is always SUM(ledger) and every
 * number on screen can be explained row by row.
 *
 *     $ledger->credit($employee, $casual, $year, 10, LeaveEntryType::Earned)
 *     $ledger->debit($employee, $casual, $year, 2, LeaveEntryType::Taken, source: $application)   (8.4)
 *     $ledger->adjust($employee, $casual, $year, -1.5, 'Counted twice in March')
 *     $ledger->reverse($application)                     withdrawn leave: undoes all its rows
 *     $ledger->closeYear($employee, $casual, $year)      year end: carry forward, the rest expires (8.5)
 *     $ledger->expireCarriedForward($today)             carried days past their expiry (8.5)
 *     $ledger->balance($employee, $casual, $year)        float
 *
 * Rules such as "may this employee take 3 days?" are NOT here: that is LeaveRules (8.3). This class
 * only refuses entries that make no sense (a negative credit, a Taken credit).
 */
class LeaveLedger
{
    private const CREDITS = [LeaveEntryType::Opening, LeaveEntryType::Earned, LeaveEntryType::CarriedForward];

    private const DEBITS = [LeaveEntryType::Taken, LeaveEntryType::Encashed, LeaveEntryType::Expired];

    /** Adds days. $date defaults to the start of the year for an opening balance, else today. */
    public function credit(
        Employee $employee,
        LeaveType $type,
        LeaveYear $year,
        float $days,
        LeaveEntryType $entryType,
        ?CarbonInterface $date = null,
        ?Model $source = null,
        ?string $note = null,
        ?CarbonInterface $expiresOn = null,
    ): LeaveLedgerEntry {
        if (! in_array($entryType, self::CREDITS, true) || $days <= 0) {
            throw new InvalidArgumentException("A credit needs a positive number of days and type Opening, Earned or CarriedForward, got {$entryType->name} {$days}.");
        }

        $date ??= $entryType === LeaveEntryType::Opening ? $year->start() : $this->today();

        return $this->write($employee->id, $type->id, $year->year, $entryType, $days, $date, $source, $note, $expiresOn);
    }

    /** Takes days away. $days is the positive number of days; it is stored as a minus. */
    public function debit(
        Employee $employee,
        LeaveType $type,
        LeaveYear $year,
        float $days,
        LeaveEntryType $entryType,
        ?CarbonInterface $date = null,
        ?Model $source = null,
        ?string $note = null,
    ): LeaveLedgerEntry {
        if (! in_array($entryType, self::DEBITS, true) || $days <= 0) {
            throw new InvalidArgumentException("A debit needs a positive number of days and type Taken, Encashed or Expired, got {$entryType->name} {$days}.");
        }

        return $this->write($employee->id, $type->id, $year->year, $entryType, -$days, $date ?? $this->today(), $source, $note);
    }

    /** A correction by HR: + adds, − takes away. Always with a note, because nothing else explains it. */
    public function adjust(Employee $employee, LeaveType $type, LeaveYear $year, float $days, string $note, ?CarbonInterface $date = null): LeaveLedgerEntry
    {
        if ($days == 0 || trim($note) === '') {
            throw new InvalidArgumentException('A correction needs a number of days other than 0 and a note.');
        }

        return $this->write($employee->id, $type->id, $year->year, LeaveEntryType::Adjustment, $days, $date ?? $this->today(), null, $note);
    }

    /**
     * Undoes everything $source wrote (e.g. a withdrawn leave application, 8.4): for each employee, type,
     * year and entry type whose rows of this source do not add up to 0, one opposite row. Running it twice
     * writes nothing the second time.
     *
     * @return int the number of rows written
     */
    public function reverse(Model $source, ?string $note = null): int
    {
        $groups = LeaveLedgerEntry::query()
            ->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())
            ->groupBy('employee_id', 'leave_type_id', 'year', 'entry_type')
            ->select('employee_id', 'leave_type_id', 'year', 'entry_type', DB::raw('SUM(days) as total'))
            ->get();

        $written = 0;
        $this->transaction(function () use ($groups, $source, $note, &$written) {
            foreach ($groups as $group) {
                $total = round((float) $group->getAttribute('total'), 2);
                if ($total != 0) {
                    $this->write($group->employee_id, $group->leave_type_id, $group->year, $group->entry_type, -$total,
                        $this->today(), $source, $note ?? __('Reversed'));
                    $written++;
                }
            }
        });

        return $written;
    }

    /**
     * Year end for one employee and leave type: what is left of $year is carried into the next year
     * (up to the type's carry_forward_limit; carried days expire after carry_forward_expiry_months), the
     * rest expires. Afterwards $year's balance is 0. Runs once per year: a second call does nothing.
     * A balance of 0 or below is left alone.
     *
     * @return float the days carried into the next year
     */
    public function closeYear(Employee $employee, LeaveType $type, LeaveYear $year): float
    {
        if ($this->isClosed($employee, $type, $year)) {
            return 0.0;
        }

        $balance = $this->balance($employee, $type, $year);
        if ($balance <= 0) {
            return 0.0;
        }

        $carry = $type->can_carry_forward ? min($balance, $type->carry_forward_limit ?? $balance) : 0.0;
        $lapse = round($balance - $carry, 2);
        $end = $year->end();
        $next = $year->next();

        $this->transaction(function () use ($employee, $type, $year, $next, $end, $carry, $lapse) {
            if ($carry > 0) {
                $this->write($employee->id, $type->id, $year->year, LeaveEntryType::CarriedForward, -$carry, $end, null,
                    __('To :year', ['year' => $next->label()]));
                $this->write($employee->id, $type->id, $next->year, LeaveEntryType::CarriedForward, $carry, $next->start(), null,
                    __('From :year', ['year' => $year->label()]),
                    $type->carry_forward_expiry_months ? $next->start()->addMonths($type->carry_forward_expiry_months) : null);
            }
            if ($lapse > 0) {
                $this->write($employee->id, $type->id, $year->year, LeaveEntryType::Expired, -$lapse, $end, null, __('Year end'));
            }
        });

        return $carry;
    }

    /**
     * Carried-forward days whose expires_on has come (≤ $today) and that were not handled yet. Carried days
     * are used FIRST: only what was not taken or encashed before the expiry date expires (and never more
     * than the balance). The Expired row points at the carried row (source), which also marks it as done;
     * when nothing is left, a 0-day row says so.
     *
     * @return int the number of carried rows handled
     */
    public function expireCarriedForward(CarbonInterface $today, ?Employee $employee = null): int
    {
        $carried = LeaveLedgerEntry::query()
            ->where('entry_type', LeaveEntryType::CarriedForward)->where('days', '>', 0)
            ->whereNotNull('expires_on')->whereDate('expires_on', '<=', $today)
            ->when($employee, fn ($q) => $q->where('employee_id', $employee->id))
            ->whereNotExists(fn ($q) => $q->from('leave_ledger as done')
                ->whereColumn('done.source_id', 'leave_ledger.id')
                ->where('done.source_type', (new LeaveLedgerEntry)->getMorphClass()))
            ->orderBy('id')
            ->get();

        foreach ($carried as $row) {
            $used = -(float) LeaveLedgerEntry::query()
                ->where('employee_id', $row->employee_id)->where('leave_type_id', $row->leave_type_id)->where('year', $row->year)
                ->whereIn('entry_type', [LeaveEntryType::Taken, LeaveEntryType::Encashed])
                ->whereDate('entry_date', '<', $row->expires_on)
                ->sum('days');
            $balance = (float) (LeaveBalance::query()
                ->where('employee_id', $row->employee_id)->where('leave_type_id', $row->leave_type_id)->where('year', $row->year)
                ->value('balance') ?? 0);
            $expire = round(max(0.0, min($row->days - $used, $balance)), 2);

            $this->write($row->employee_id, $row->leave_type_id, $row->year, LeaveEntryType::Expired, -$expire,
                CarbonImmutable::parse($row->expires_on), $row,
                $expire > 0 ? __('Carried days expired') : __('Carried days were used before they expired'));
        }

        return $carried->count();
    }

    public function balance(Employee $employee, LeaveType $type, LeaveYear $year): float
    {
        return (float) (LeaveBalance::query()
            ->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', $year->year)
            ->value('balance') ?? 0);
    }

    /** @return Collection<int, LeaveBalance> keyed by leave_type_id */
    public function balances(Employee $employee, LeaveYear $year): Collection
    {
        return LeaveBalance::query()->where('employee_id', $employee->id)->where('year', $year->year)->get()->keyBy('leave_type_id');
    }

    /** Rebuilds the cached balance row from the ledger. Called after every write; public for repairs. */
    public function refresh(int $employeeId, int $leaveTypeId, int $year): LeaveBalance
    {
        $sums = LeaveLedgerEntry::query()
            ->where('employee_id', $employeeId)->where('leave_type_id', $leaveTypeId)->where('year', $year)
            ->groupBy('entry_type')
            ->selectRaw('entry_type, SUM(days) as total')
            ->toBase()->get();

        $values = array_fill_keys(LeaveBalance::AMOUNTS, 0.0);
        foreach ($sums as $row) {
            $type = LeaveEntryType::from((int) $row->entry_type);
            $total = $row->total;
            $values[$type->balanceColumn()] = $type->isUsage() ? -(float) $total : (float) $total;
            $values['balance'] += (float) $total;
        }

        return LeaveBalance::updateOrCreate(
            ['employee_id' => $employeeId, 'leave_type_id' => $leaveTypeId, 'year' => $year],
            array_map(fn (float $v) => round($v, 2), $values),
        );
    }

    /** Year already closed = it has a year-end row (carried out or expired on its last day, no source). */
    private function isClosed(Employee $employee, LeaveType $type, LeaveYear $year): bool
    {
        return LeaveLedgerEntry::query()
            ->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', $year->year)
            ->whereIn('entry_type', [LeaveEntryType::CarriedForward, LeaveEntryType::Expired])
            ->where('days', '<', 0)->whereNull('source_type')
            ->whereDate('entry_date', $year->end())
            ->exists();
    }

    private function write(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        LeaveEntryType $entryType,
        float $days,
        CarbonInterface $date,
        ?Model $source,
        ?string $note,
        ?CarbonInterface $expiresOn = null,
    ): LeaveLedgerEntry {
        return $this->transaction(function () use ($employeeId, $leaveTypeId, $year, $entryType, $days, $date, $source, $note, $expiresOn) {
            $entry = LeaveLedgerEntry::create([
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
                'entry_type' => $entryType,
                'days' => round($days, 2),
                'entry_date' => $date->toDateString(),
                'expires_on' => $expiresOn?->toDateString(),
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'note' => $note,
                'created_by_user_id' => auth()->id(),
            ]);
            $this->refresh($employeeId, $leaveTypeId, $year);

            return $entry;
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function transaction(callable $callback): mixed
    {
        return (new LeaveLedgerEntry)->getConnection()->transaction($callback);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(settings('timezone', config('app.timezone')))->startOfDay();
    }
}
