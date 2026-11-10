<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;

/**
 * Saves cells of a roster's grid. The ONE way cells are written: by the grid editor and by the Excel import. [TA-03]
 *
 *     $cells = [employee_id => ['2026-12-01' => '3' (shift id) | 'off' | 'call' | '' (not planned), ...]]
 *
 * Only the given employees and dates change; everything else stays. Like the other actions:
 *     if ($problem = $action->problem($roster, $cells)) { show it } else { $action->handle($roster, $cells) }
 *
 * Cells are written in bulk (one delete, one insert), not one model at a time: a month for 50 people is
 * 1,550 cells. So instead of one activity-log entry per cell, the roster gets ONE entry with the counts.
 */
class SaveRosterCells
{
    public const OFF = 'off';

    public const ON_CALL = 'call';

    /**
     * Why these cells cannot be saved, in words for the user, or null when all is well.
     *
     * @param  array<int|string, array<string, string|null>>  $cells
     */
    public function problem(Roster $roster, array $cells): ?string
    {
        if (! $roster->isEditable()) {
            return __('This roster is waiting for approval or already approved, so it cannot be changed.');
        }

        $employees = $roster->gridEmployees()->keyBy('id');
        $dates = array_flip(array_map(fn ($d) => $d->toDateString(), $roster->dates()));
        $shiftIds = $this->allowedShiftIds($roster);

        foreach ($cells as $employeeId => $row) {
            if (! $employees->has((int) $employeeId)) {
                return __('Employee #:id is not in this roster\'s org unit.', ['id' => $employeeId]);
            }
            foreach ($row as $date => $value) {
                if (! isset($dates[$date])) {
                    return __(':date is outside the roster period.', ['date' => $date]);
                }
                $value = (string) $value;
                if (! in_array($value, ['', self::OFF, self::ON_CALL], true) && ! isset($shiftIds[(int) $value])) {
                    return __('Unknown or inactive shift in the cell of :code on :date.', ['code' => $employees[(int) $employeeId]->code, 'date' => $date]);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, array<string, string|null>>  $cells
     * @return int how many cells are planned now (in the whole roster)
     */
    public function handle(Roster $roster, array $cells): int
    {
        if ($cells === []) {
            return $roster->items()->count();
        }

        $roster->getConnection()->transaction(function () use ($roster, $cells) {
            $employeeIds = array_map('intval', array_keys($cells));
            $dates = array_keys(array_replace(...array_values($cells)));

            // Keep a cell's own start / end times when its shift did not change (not editable in the grid).
            $old = $roster->items()->whereIn('employee_id', $employeeIds)->whereIn('date', $dates)->get()
                ->keyBy(fn (RosterItem $i) => $i->employee_id.'|'.$i->date->toDateString());
            $before = $roster->items()->count();

            $rows = [];
            $now = now();
            foreach ($cells as $employeeId => $row) {
                foreach ($row as $date => $value) {
                    $value = (string) $value;
                    if ($value === '') {
                        continue;
                    }
                    $shiftId = ctype_digit($value) ? (int) $value : null;
                    $previous = $old[$employeeId.'|'.$date] ?? null;
                    $keepTimes = $previous !== null && $shiftId !== null && $previous->shift_id === $shiftId;

                    $rows[] = [
                        'roster_id' => $roster->id,
                        'employee_id' => (int) $employeeId,
                        'date' => $date,
                        'shift_id' => $shiftId,
                        'is_off' => $value === self::OFF,
                        'is_on_call' => $value === self::ON_CALL,
                        'start_time' => $keepTimes ? $previous->start_time : null,
                        'end_time' => $keepTimes ? $previous->end_time : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            $roster->items()->whereIn('employee_id', $employeeIds)->whereIn('date', $dates)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                RosterItem::query()->insert($chunk);
            }

            $after = $roster->items()->count();
            activity()->performedOn($roster)->event('updated')
                ->withChanges(['old' => ['planned_cells' => $before], 'attributes' => ['planned_cells' => $after]])
                ->log('updated');
        });

        return $roster->items()->count();
    }

    /**
     * Active shifts, plus shifts this roster already uses (made inactive since: unchanged cells stay valid).
     *
     * @return array<int, true>
     */
    private function allowedShiftIds(Roster $roster): array
    {
        $used = $roster->items()->whereNotNull('shift_id')->distinct()->pluck('shift_id')->all();

        return array_fill_keys(
            Shift::query()->where('is_active', true)->orWhereIn('id', $used)->pluck('id')->all(),
            true,
        );
    }
}
