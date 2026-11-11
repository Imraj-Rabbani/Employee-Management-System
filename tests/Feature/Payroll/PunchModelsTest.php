<?php

namespace Tests\Feature\Payroll;

use App\Core\Tenancy\TenantManager;
use App\Modules\Core\Models\Activity;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Models\Device;
use App\Modules\Payroll\Models\PunchLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

/**
 * Task 7.3a: devices + punch_logs. [TA-04]
 */
class PunchModelsTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->switchTo($this->createMigratedTenant());
    }

    public function test_source_numbers_are_legacys(): void
    {
        $this->assertSame([0, 1, 2, 3, 4], PunchSource::values());
        $this->assertSame('Device', PunchSource::Device->label());

        $punch = PunchLog::factory()->create(['source' => PunchSource::Web])->fresh();
        $this->assertSame(PunchSource::Web, $punch->source);
    }

    public function test_a_device_defaults_to_the_zkteco_port_and_sync_times_are_not_logged(): void
    {
        $device = Device::factory()->create();

        $this->assertSame(4370, $device->fresh()->port);
        $this->assertTrue($device->fresh()->is_active);

        $device->forceFill(['last_synced_at' => now()])->save();
        $this->assertSame(1, Activity::where('subject_type', $device->getMorphClass())->count());   // only "created"
    }

    public function test_punches_write_no_activity_log(): void
    {
        PunchLog::factory()->count(3)->create();

        $this->assertSame(0, Activity::where('subject_type', (new PunchLog)->getMorphClass())->count());
    }

    public function test_the_same_device_punch_is_stored_once(): void
    {
        $device = Device::factory()->create();
        $punch = ['device_id' => $device->id, 'access_code' => '1001', 'punched_at' => '2026-12-01 08:55:00', 'source' => PunchSource::Device, 'employee_id' => null];
        PunchLog::create($punch);

        // Re-syncing sends it again: insertOrIgnore (used by the sync) skips it …
        $this->assertSame(0, PunchLog::query()->insertOrIgnore([['device_id' => $device->id, 'access_code' => '1001', 'punched_at' => '2026-12-01 08:55:00', 'source' => 2]]));

        // … and a plain insert is refused.
        $this->expectException(QueryException::class);
        PunchLog::create($punch);
    }

    public function test_punches_in_a_time_window_in_order(): void
    {
        $employee = Employee::factory()->create();
        PunchLog::factory()->for($employee)->create(['punched_at' => '2026-12-02 06:05']);   // night shift's out
        PunchLog::factory()->for($employee)->create(['punched_at' => '2026-12-01 21:50']);   // its in
        PunchLog::factory()->for($employee)->create(['punched_at' => '2026-12-02 21:55']);   // next night: outside
        PunchLog::factory()->create(['punched_at' => '2026-12-01 22:00']);                   // someone else

        $times = PunchLog::forEmployeeBetween($employee, Carbon::parse('2026-12-01 18:00'), Carbon::parse('2026-12-02 12:00'))
            ->pluck('punched_at')->map->format('Y-m-d H:i')->all();

        $this->assertSame(['2026-12-01 21:50', '2026-12-02 06:05'], $times);
    }

    public function test_unmatched_punches_are_linked_by_access_code_later(): void
    {
        $karim = Employee::factory()->create(['access_code' => '1001']);
        Employee::factory()->create(['access_code' => '2002']);
        Employee::factory()->create(['access_code' => '2002']);   // same code twice: ambiguous

        $known = PunchLog::factory()->create(['employee_id' => null, 'access_code' => '1001']);
        $ambiguous = PunchLog::factory()->create(['employee_id' => null, 'access_code' => '2002']);
        $unknown = PunchLog::factory()->create(['employee_id' => null, 'access_code' => '9999']);

        $this->assertSame(1, PunchLog::linkEmployees());
        $this->assertSame($karim->id, $known->fresh()->employee_id);
        $this->assertNull($ambiguous->fresh()->employee_id);
        $this->assertNull($unknown->fresh()->employee_id);
    }
}
