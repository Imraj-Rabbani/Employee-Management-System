<?php

namespace App\Modules\Payroll\Data;

use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\DayType;
use Carbon\CarbonImmutable;

/** What AttendanceProcessor decided for one day: the columns of attendance_days. All durations in minutes. */
final readonly class DayResult
{
    public function __construct(
        public AttendanceStatus $status,
        public DayType $dayType,
        public ?int $shiftId = null,
        public ?CarbonImmutable $inAt = null,
        public ?CarbonImmutable $outAt = null,
        public int $punchCount = 0,
        public int $workedMinutes = 0,
        public int $lateMinutes = 0,
        public bool $isLate = false,
        public bool $usedGrace = false,
        public int $earlyExitMinutes = 0,
        public int $otPreMinutes = 0,
        public int $otPostMinutes = 0,
        public int $otMinutes = 0,
        public ?string $note = null,
    ) {}

    /** @return array<string, mixed> for AttendanceDay::updateOrCreate() */
    public function toAttributes(): array
    {
        return [
            'status' => $this->status, 'day_type' => $this->dayType, 'shift_id' => $this->shiftId,
            'in_at' => $this->inAt, 'out_at' => $this->outAt, 'punch_count' => $this->punchCount,
            'worked_minutes' => $this->workedMinutes, 'late_minutes' => $this->lateMinutes,
            'is_late' => $this->isLate, 'used_grace' => $this->usedGrace, 'early_exit_minutes' => $this->earlyExitMinutes,
            'ot_pre_minutes' => $this->otPreMinutes, 'ot_post_minutes' => $this->otPostMinutes, 'ot_minutes' => $this->otMinutes,
            'note' => $this->note,
        ];
    }
}
