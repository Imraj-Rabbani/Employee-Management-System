<?php

namespace Tests\Feature\Payroll;

use App\Core\Tenancy\TenantManager;
use App\Modules\Core\Models\Activity;
use App\Modules\Payroll\Enums\AttendanceStatus;
use App\Modules\Payroll\Enums\DayType;
use App\Modules\Payroll\Models\AttendanceDay;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

/**
 * Task 7.4a: attendance_days (the result of processing a day). [TA-05]
 */
class AttendanceDayModelTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->switchTo($this->createMigratedTenant());
    }

    public function test_casts_defaults_and_no_activity_log(): void
    {
        $day = AttendanceDay::factory()->create(['day_type' => DayType::Festival, 'status' => AttendanceStatus::HalfDay])->fresh();

        $this->assertSame(DayType::Festival, $day->day_type);
        $this->assertSame(AttendanceStatus::HalfDay, $day->status);
        $this->assertSame('2026-12-01 09:00', $day->in_at->format('Y-m-d H:i'));
        $this->assertSame(0, $day->ot_minutes);
        $this->assertNull($day->ot_approved_minutes);
        $this->assertFalse($day->is_locked);
        $this->assertSame(0, Activity::where('subject_type', $day->getMorphClass())->count());
    }

    public function test_one_result_per_employee_and_day(): void
    {
        $day = AttendanceDay::factory()->create();

        $this->expectException(QueryException::class);
        AttendanceDay::factory()->create(['employee_id' => $day->employee_id, 'date' => $day->date]);
    }

    public function test_enums_and_hours_label(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6], AttendanceStatus::values());
        $this->assertSame('danger', AttendanceStatus::Absent->color());
        $this->assertSame([1, 2, 3, 4], DayType::values());
        $this->assertSame('8:45', AttendanceDay::hoursLabel(525));
        $this->assertSame('0:05', AttendanceDay::hoursLabel(5));
    }
}
