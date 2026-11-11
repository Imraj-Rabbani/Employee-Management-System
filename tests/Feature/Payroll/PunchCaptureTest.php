<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Exports\TemplateSheet;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Contracts\PunchDeviceClient;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Exceptions\DeviceException;
use App\Modules\Payroll\Imports\PunchFileReader;
use App\Modules\Payroll\Livewire\Attendance\DeviceForm;
use App\Modules\Payroll\Livewire\Attendance\PunchSettings;
use App\Modules\Payroll\Livewire\Attendance\PunchUpload;
use App\Modules\Payroll\Livewire\Ess\WebPunch;
use App\Modules\Payroll\Models\Device;
use App\Modules\Payroll\Models\PunchLog;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Services\DeviceSync;
use App\Modules\Payroll\Services\GeoDistance;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 7.3b–e: devices + sync, punch upload, punch list, Self Service web punch. [TA-04]
 */
class PunchCaptureTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    /** @var array<int, list<array{code: string, punched_at: Carbon}>|string> device id => punches, or an error message */
    private array $deviceData = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-12-02 10:00');
        $this->tenant = $this->createMigratedTenant(['modules' => ['hrm', 'payroll', 'workflow']]);
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        // A fake device client: answers from $this->deviceData instead of the network.
        $test = $this;
        $this->app->instance(PunchDeviceClient::class, new class($test) implements PunchDeviceClient
        {
            public function __construct(private PunchCaptureTest $test) {}

            public function fetch(Device $device): array
            {
                $data = $this->test->dataFor($device);
                if (is_string($data)) {
                    throw new DeviceException($data);
                }

                return $data;
            }
        });
    }

    /** @return list<array{code: string, punched_at: Carbon}>|string */
    public function dataFor(Device $device): array|string
    {
        return $this->deviceData[$device->id] ?? [];
    }

    private function punch(string $code, string $at): array
    {
        return ['code' => $code, 'punched_at' => Carbon::parse($at)];
    }

    // ---- devices + sync (7.3b, 7.3d) --------------------------------------------------------------

    public function test_devices_are_set_up_on_their_tab(): void
    {
        $this->get('/payroll/attendance/devices')->assertOk()->assertSee('Web punch (Self Service)');

        Livewire::test(DeviceForm::class)
            ->dispatch('create-device')
            ->set('form.name', 'Main gate')
            ->set('form.ip', '192.168.1.300')
            ->call('save')
            ->assertHasErrors(['form.ip'])
            ->set('form.ip', '192.168.1.201')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(4370, Device::sole()->port);

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.punch.manage']);
        $this->get('/payroll/attendance/devices')->assertForbidden();
    }

    public function test_a_sync_stores_new_punches_once_links_employees_and_skips_old_ones(): void
    {
        $karim = Employee::factory()->create(['access_code' => '1001']);
        $device = Device::factory()->create();
        $this->deviceData[$device->id] = [
            $this->punch('1001', '2026-12-01 08:55'),
            $this->punch('1001', '2026-12-01 18:02'),
            $this->punch('7777', '2026-12-01 09:10'),   // nobody has this code (yet)
            $this->punch('1001', '2026-09-01 09:00'),   // older than 62 days: not stored
        ];

        $this->assertSame(3, app(DeviceSync::class)->sync($device));
        $this->assertSame(0, app(DeviceSync::class)->sync($device));   // the device sends everything again

        $this->assertSame(2, PunchLog::where('employee_id', $karim->id)->count());
        $this->assertNull(PunchLog::where('access_code', '7777')->sole()->employee_id);
        $this->assertSame(PunchSource::Device, PunchLog::first()->source);
        $this->assertNotNull($device->fresh()->last_synced_at);
        $this->assertNull($device->fresh()->last_sync_error);
    }

    public function test_a_failing_device_records_its_error_and_the_others_still_sync(): void
    {
        $broken = Device::factory()->create(['name' => 'Broken']);
        $working = Device::factory()->create(['name' => 'Working']);
        $off = Device::factory()->create(['name' => 'Off', 'is_active' => false]);
        $this->deviceData[$broken->id] = 'Cannot connect to 10.0.0.9:4370.';
        $this->deviceData[$working->id] = [$this->punch('1001', '2026-12-01 08:55')];
        $this->deviceData[$off->id] = [$this->punch('1002', '2026-12-01 08:56')];

        $exit = Artisan::call('payroll:sync-devices');

        $this->assertSame(1, $exit);   // FAILURE: one device failed
        $this->assertSame('Cannot connect to 10.0.0.9:4370.', $broken->fresh()->last_sync_error);
        $this->assertSame(['1001'], PunchLog::pluck('access_code')->all());   // working yes, inactive no

        // "Sync now" on the broken one shows the error.
        Livewire::test(DeviceForm::class)
            ->dispatch('edit-device', id: $broken->id)
            ->call('syncNow')
            ->assertDispatched('toast', variant: 'danger');
    }

    public function test_the_sync_runs_every_five_minutes_for_every_company(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'tenants:run payroll:sync-devices'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ---- upload + list (7.3c) ---------------------------------------------------------------------

    public function test_the_file_reader_accepts_both_layouts_and_lists_every_problem(): void
    {
        $reader = new PunchFileReader;

        [$punches, $problems] = $reader->parse([
            ['access_code' => '1001', 'punched_at' => '2026-12-01 08:55'],
            ['access_code' => '1002', 'punched_at' => '2026-12-01 09:01:30'],
        ]);
        $this->assertSame([], $problems);
        $this->assertSame('2026-12-01 09:01:30', $punches[1]['punched_at']->format('Y-m-d H:i:s'));

        [$punches] = $reader->parse([['access_code' => 1001, 'date' => '2026-12-01', 'time' => '8:05']]);
        $this->assertSame(['1001', '2026-12-01 08:05'], [$punches[0]['code'], $punches[0]['punched_at']->format('Y-m-d H:i')]);

        [$punches, $problems] = $reader->parse([
            ['access_code' => '', 'punched_at' => '2026-12-01 08:55'],
            ['access_code' => '1001', 'punched_at' => '01/12/2026 08:55'],
            ['access_code' => '1001', 'punched_at' => '2026-12-09 08:55'],   // future (today is 2 Dec)
        ]);
        $this->assertSame([], $punches);   // one problem = nothing
        $this->assertCount(3, $problems);
        $this->assertStringContainsString('Row 2', $problems[0]);
        $this->assertStringContainsString('Row 4', $problems[2]);

        [, $problems] = $reader->parse([['code' => '1001', 'when' => 'x']]);
        $this->assertStringContainsString('must name the columns', $problems[0]);
    }

    public function test_an_upload_stores_new_punches_and_skips_ones_already_there(): void
    {
        // Real time: Livewire treats a temporary upload as expired when the test clock is far from the file's age.
        $this->travelBack();
        $karim = Employee::factory()->create(['access_code' => '1001']);
        PunchLog::factory()->for($karim)->create(['access_code' => '1001', 'punched_at' => '2026-11-01 08:55']);

        $file = UploadedFile::fake()->createWithContent('punches.xlsx', Excel::raw(new TemplateSheet('Punches', ['access_code', 'punched_at'], [
            ['1001', '2026-11-01 08:55'],   // already there
            ['1001', '2026-11-01 18:00'],
            ['2002', '2026-11-01 09:00'],
        ]), ExcelFormat::XLSX));

        Livewire::test(PunchUpload::class)
            ->set('file', $file)
            ->call('upload')
            ->assertSet('problems', [])
            ->assertDispatched('toast', message: '2 punches added, 1 were already there.');

        $uploaded = PunchLog::where('source', PunchSource::Csv)->get();
        $this->assertCount(2, $uploaded);
        $this->assertCount(1, $uploaded->pluck('batch')->unique());
        $this->assertSame(auth()->id(), $uploaded->first()->created_by_user_id);
        $this->assertSame(2, PunchLog::where('employee_id', $karim->id)->count());

        // Later, Rafi gets code 2002: "Match unmatched punches" links his.
        $rafi = Employee::factory()->create(['access_code' => '2002']);
        Livewire::test(PunchUpload::class)->call('linkUnmatched');
        $this->assertSame(1, PunchLog::where('employee_id', $rafi->id)->count());
    }

    public function test_the_punch_list_shows_only_visible_employees(): void
    {
        $factory = OrgUnit::factory()->create();
        $office = OrgUnit::factory()->create();
        PunchLog::factory()->for(Employee::factory()->inUnit($factory)->create(['code' => 'E-FAC']))->create();
        PunchLog::factory()->for(Employee::factory()->inUnit($office)->create(['code' => 'E-OFF']))->create();
        PunchLog::factory()->create(['employee_id' => null, 'access_code' => 'X-UNMATCHED']);

        $this->get('/payroll/attendance/punches')->assertOk()->assertSee('E-FAC')->assertSee('E-OFF')->assertSee('X-UNMATCHED');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.punch.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => $factory->id]);
        $this->get('/payroll/attendance/punches')->assertOk()->assertSee('E-FAC')->assertDontSee('E-OFF')->assertDontSee('X-UNMATCHED');
    }

    // ---- web punch (7.3e) ----------------------------------------------------------------------------

    private function me(array $attributes = []): Employee
    {
        $user = User::factory()->for($this->tenant)->create();
        $employee = Employee::factory()->create(['access_code' => '1001', ...$attributes]);
        $employee->forceFill(['user_id' => $user->id])->save();
        $this->actingAs($user);

        return $employee;
    }

    private function allowWebPunch(bool $requireLocation = true): void
    {
        Livewire::test(PunchSettings::class)->set('webPunch', true)->set('requireLocation', $requireLocation)->call('save');
    }

    public function test_web_punch_is_off_until_the_company_allows_it(): void
    {
        $me = $this->me();

        Livewire::test(WebPunch::class)->assertSee('switched off')->call('punch', 23.78, 90.27)->assertDispatched('toast', variant: 'danger');
        $this->assertSame(0, PunchLog::count());

        $this->actingAsTenantUser(tenant: $this->tenant);
        $this->allowWebPunch();
        $this->actingAs(User::findOrFail($me->user_id));

        $this->get('/ess/attendance')->assertOk()->assertSee('Punch now');
        Livewire::test(WebPunch::class)->call('punch', 23.78, 90.27)->assertDispatched('toast', variant: 'success');

        $punch = PunchLog::sole();
        $this->assertSame([$me->id, '1001', PunchSource::Web], [$punch->employee_id, $punch->access_code, $punch->source]);
        $this->assertSame('23.7800000', $punch->latitude);
        $this->assertNull($punch->distance_m);   // no geofence on the shift
    }

    public function test_location_rules_geofence_and_double_clicks(): void
    {
        $this->allowWebPunch(requireLocation: false);
        $office = ['lat' => 23.7808875, 'lng' => 90.2792371];
        $fenced = Shift::factory()->create([
            'geofence_enabled' => true, 'geofence_lat' => $office['lat'], 'geofence_lng' => $office['lng'], 'geofence_radius_m' => 200,
        ]);
        $me = $this->me(['shift_id' => $fenced->id]);

        // Geofenced shift: a location is needed even though the company does not require one.
        Livewire::test(WebPunch::class)->call('punch')->assertDispatched('toast', variant: 'danger');

        // ~1.1 km north: too far.
        Livewire::test(WebPunch::class)->call('punch', $office['lat'] + 0.01, $office['lng'])
            ->assertDispatched('toast', message: 'You are 1,112 m away from your workplace. Punching is allowed within 200 m.');
        $this->assertSame(0, PunchLog::count());

        // ~55 m away: fine, distance kept.
        Livewire::test(WebPunch::class)->call('punch', $office['lat'] + 0.0005, $office['lng'])->assertDispatched('toast', variant: 'success');
        $this->assertSame(56, PunchLog::sole()->distance_m);

        // Again 30 seconds later: refused.
        $this->travel(30)->seconds();
        Livewire::test(WebPunch::class)->call('punch', $office['lat'], $office['lng'])->assertDispatched('toast', message: 'You punched less than a minute ago.');
        $this->assertSame(1, PunchLog::count());

        // An approved roster with an unfenced shift today: no geofence, and no location needed (company setting off).
        $roster = Roster::factory()->approved()->create(['from_date' => '2026-12-02', 'to_date' => '2026-12-02']);
        RosterItem::factory()->for($roster)->create(['employee_id' => $me->id, 'date' => '2026-12-02', 'shift_id' => Shift::factory()->create()->id]);
        $this->travel(2)->minutes();
        Livewire::test(WebPunch::class)->call('punch')->assertDispatched('toast', variant: 'success');
        $this->assertSame(2, PunchLog::count());
    }

    public function test_a_login_without_an_employee_cannot_punch(): void
    {
        $this->allowWebPunch();
        $this->actingAs(User::factory()->for($this->tenant)->create());

        $this->get('/ess/attendance')->assertOk()->assertSee('No employee record');
        Livewire::test(WebPunch::class)->call('punch', 23.78, 90.27)->assertDispatched('toast', variant: 'danger');
        $this->assertSame(0, PunchLog::count());
    }

    public function test_distance_in_metres(): void
    {
        $this->assertSame(0, GeoDistance::metres(23.78, 90.27, 23.78, 90.27));
        $this->assertSame(111195, GeoDistance::metres(0, 0, 1, 0));   // one degree of latitude
    }
}
