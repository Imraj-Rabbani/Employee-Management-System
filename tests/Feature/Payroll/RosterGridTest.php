<?php

namespace Tests\Feature\Payroll;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Activity;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Exports\TemplateSheet;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Enums\Weekday;
use App\Modules\Payroll\Exports\RosterExport;
use App\Modules\Payroll\Livewire\Attendance\RosterGrid;
use App\Modules\Payroll\Models\Holiday;
use App\Modules\Payroll\Models\HolidayProfile;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Payroll\Models\RosterItem;
use App\Modules\Payroll\Models\Shift;
use App\Modules\Payroll\Models\WeekendProfile;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 7.2c–e: roster grid (save, fill), send for approval, plan a change, Excel export / import. [TA-03]
 *
 * Roster: Factory (and its sub-unit Line 1), 1–7 December 2026 (Tue … Mon; 4 Dec = Friday, 5 Dec = Saturday).
 */
class RosterGridTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Roster $roster;

    private Employee $karim;   // Factory

    private Employee $salma;   // Line 1 (below Factory)

    private Employee $outsider;   // Office

    private Shift $morning;

    private Shift $night;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator

        $factory = OrgUnit::factory()->create(['name' => 'Factory']);
        $line = OrgUnit::factory()->childOf($factory)->create(['name' => 'Line 1']);
        $office = OrgUnit::factory()->create(['name' => 'Office']);

        $this->karim = Employee::factory()->inUnit($factory)->create(['code' => 'E-001', 'first_name' => 'Karim']);
        $this->salma = Employee::factory()->inUnit($line)->create(['code' => 'E-002', 'first_name' => 'Salma']);
        $this->outsider = Employee::factory()->inUnit($office)->create(['code' => 'E-003', 'first_name' => 'Rafi']);

        $this->morning = Shift::factory()->create(['code' => 'M', 'name' => 'Morning', 'start_time' => '06:00', 'end_time' => '14:00']);
        $this->night = Shift::factory()->overnight()->create(['code' => 'N']);

        $this->roster = Roster::factory()->create(['org_unit_id' => $factory->id, 'from_date' => '2026-12-01', 'to_date' => '2026-12-07']);
    }

    /** @return array<int, array<string, string>> */
    private function grid(): array
    {
        return $this->roster->cellMatrix();
    }

    private function useWorkflow(): User
    {
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('roster')?->id, 'name' => 'Roster']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => Role::administrator()?->id]);
        $approver = User::factory()->for($this->tenant)->create();
        Role::administrator()?->assignTo($approver);

        return $approver;
    }

    // ---- grid ---------------------------------------------------------------------------------

    public function test_the_grid_has_the_unit_and_its_sub_units_for_every_day(): void
    {
        $grid = $this->grid();

        $this->assertSame([$this->karim->id, $this->salma->id], array_keys($grid));   // not the Office employee
        $this->assertCount(7, $grid[$this->karim->id]);
        $this->assertSame('', $grid[$this->karim->id]['2026-12-01']);

        $this->get(route('payroll.attendance.rosters.show', $this->roster->id))
            ->assertOk()->assertSee('Karim')->assertSee('Salma')->assertDontSee('Rafi')
            ->assertSee('Fill empty cells')->assertSee('Send for approval');
    }

    public function test_cells_are_saved_changed_and_cleared_with_one_log_entry(): void
    {
        $grid = $this->grid();
        $grid[$this->karim->id]['2026-12-01'] = (string) $this->morning->id;
        $grid[$this->karim->id]['2026-12-02'] = (string) $this->night->id;
        $grid[$this->salma->id]['2026-12-04'] = 'off';
        $grid[$this->salma->id]['2026-12-05'] = 'call';

        Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])
            ->call('save', $grid)
            ->assertReturned(fn ($saved) => $saved[$this->salma->id]['2026-12-05'] === 'call')
            ->assertDispatched('toast');

        $this->assertSame(4, $this->roster->items()->count());
        $off = $this->roster->items()->where('employee_id', $this->salma->id)->whereDate('date', '2026-12-04')->sole();
        $this->assertTrue($off->is_off);
        $this->assertNull($off->shift_id);

        // Clear one, change one.
        $grid[$this->karim->id]['2026-12-01'] = '';
        $grid[$this->karim->id]['2026-12-02'] = (string) $this->morning->id;
        Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])->call('save', $grid);

        $this->assertSame(3, $this->roster->items()->count());
        $this->assertSame((string) $this->morning->id, $this->grid()[$this->karim->id]['2026-12-02']);
        $this->assertSame(2, Activity::query()->where('subject_type', $this->roster->getMorphClass())->where('subject_id', $this->roster->id)->where('event', 'updated')->count());
    }

    public function test_bad_cells_are_refused_and_nothing_is_saved(): void
    {
        $inactive = Shift::factory()->create(['is_active' => false]);

        foreach ([
            [$this->outsider->id, '2026-12-01', (string) $this->morning->id],   // not in the unit
            [$this->karim->id, '2026-12-08', (string) $this->morning->id],       // after the period
            [$this->karim->id, '2026-12-01', (string) $inactive->id],            // inactive shift
            [$this->karim->id, '2026-12-01', 'holiday'],                          // not a value
        ] as [$employeeId, $date, $value]) {
            Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])
                ->call('save', [$employeeId => [$date => $value]])
                ->assertReturned(null)
                ->assertDispatched('toast', variant: 'danger');
        }

        $this->assertSame(0, RosterItem::count());
    }

    public function test_fill_empty_cells_uses_holidays_weekends_own_and_default_shift(): void
    {
        Shift::factory()->create(['code' => 'G', 'is_default' => true]);
        $weekend = WeekendProfile::factory()->create(['day_one' => Weekday::Friday, 'day_two' => Weekday::Saturday]);
        $calendar = HolidayProfile::factory()->hasAttached(Holiday::factory()->state(['date' => '2026-12-16', 'title' => 'Victory Day', 'repeats_yearly' => true])->create(['date' => '2025-12-16']))->create();
        $this->karim->update(['shift_id' => $this->night->id, 'weekend_profile_id' => $weekend->id]);
        $this->salma->update(['holiday_profile_id' => $calendar->id]);
        $this->roster->update(['to_date' => '2026-12-16']);

        $grid = $this->grid();
        $grid[$this->karim->id]['2026-12-01'] = (string) $this->morning->id;   // already planned: stays

        Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])
            ->call('fillEmpty', $grid)
            ->assertReturned(function (array $filled) {
                $k = $filled[$this->karim->id];
                $s = $filled[$this->salma->id];

                return $k['2026-12-01'] === (string) $this->morning->id   // kept
                    && $k['2026-12-02'] === (string) $this->night->id     // own shift
                    && $k['2026-12-04'] === 'off' && $k['2026-12-05'] === 'off'   // Friday, Saturday
                    && $s['2026-12-04'] !== 'off'                          // Salma has no weekend profile …
                    && $s['2026-12-16'] === 'off'                          // … but a yearly holiday
                    && $s['2026-12-02'] === (string) Shift::default()?->id;   // default shift
            });

        $this->assertSame(0, RosterItem::count());   // filling does not save
    }

    // ---- approval -----------------------------------------------------------------------------

    public function test_sending_needs_saved_cells_and_a_workflow(): void
    {
        Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])
            ->call('submit')
            ->assertDispatched('toast', variant: 'danger');   // nothing planned yet

        RosterItem::factory()->for($this->roster)->create(['employee_id' => $this->karim->id, 'date' => '2026-12-01', 'shift_id' => $this->morning->id]);
        Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])
            ->call('submit')
            ->assertDispatched('toast', variant: 'danger');   // no "roster" workflow set up
        $this->assertTrue($this->roster->fresh()->isDraft());

        $approver = $this->useWorkflow();
        Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])
            ->call('submit')
            ->assertRedirect(route('payroll.attendance.rosters.show', $this->roster->id));
        $this->assertSame(ApprovalStatus::Pending, $this->roster->fresh()->approvalStatus());

        // Pending: the grid is read-only, saving is refused.
        $this->get(route('payroll.attendance.rosters.show', $this->roster->id))->assertSee('Waiting for approval')->assertDontSee('Fill empty cells');
        Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])
            ->call('save', [$this->karim->id => ['2026-12-02' => 'off']])
            ->assertReturned(null);

        // The approver sees the grid, then approves: the plan counts.
        $html = view('payroll::approvals.roster', ['document' => $this->roster->fresh()])->render();
        $this->assertStringContainsString('E-001', $html);
        $this->assertStringContainsString('>M<', str_replace(' ', '', $html));

        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Approved, $approver);
        $this->assertTrue($this->roster->fresh()->isApproved());
        $this->assertSame($this->morning->id, RosterItem::planFor($this->karim, Carbon::parse('2026-12-01'))?->shift_id);
    }

    public function test_a_change_of_an_approved_roster_is_a_copy_that_wins_once_approved(): void
    {
        $approver = $this->useWorkflow();
        RosterItem::factory()->for($this->roster)->create(['employee_id' => $this->karim->id, 'date' => '2026-12-01', 'shift_id' => $this->morning->id]);
        $this->roster->submitForApproval(auth()->user());
        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Approved, $approver);

        Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])->call('planChange');

        $change = Roster::query()->whereKeyNot($this->roster->id)->sole();
        $this->assertSame($this->roster->id, $change->replaces_roster_id);
        $this->assertTrue($change->isDraft());
        $this->assertSame($this->roster->cellMatrix(), $change->cellMatrix());

        // Change Karim's 1 December to Night, send and approve.
        Livewire::test(RosterGrid::class, ['rosterId' => $change->id])
            ->call('save', [$this->karim->id => ['2026-12-01' => (string) $this->night->id]]);
        $change->submitForApproval(auth()->user());
        $this->travel(1)->minutes();
        app(WorkflowEngine::class)->respond($change->approvals()->sole(), ApprovalStatus::Approved, $approver);

        $this->assertSame($this->night->id, RosterItem::planFor($this->karim, Carbon::parse('2026-12-01'))?->shift_id);
    }

    // ---- Excel --------------------------------------------------------------------------------

    public function test_export_has_a_row_per_employee_and_a_column_per_day(): void
    {
        RosterItem::factory()->for($this->roster)->create(['employee_id' => $this->salma->id, 'date' => '2026-12-03', 'shift_id' => $this->night->id]);
        RosterItem::factory()->for($this->roster)->off()->create(['employee_id' => $this->salma->id, 'date' => '2026-12-04']);

        $sheet = (new RosterExport($this->roster))->sheets()[0];
        $this->assertInstanceOf(TemplateSheet::class, $sheet);
        $this->assertSame(['Code', 'Name', '2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-05', '2026-12-06', '2026-12-07'], $sheet->headings());
        $this->assertSame(['E-002', $this->salma->name, '', '', 'N', 'OFF', '', '', ''], $sheet->array()[1]);

        $this->get(route('payroll.attendance.rosters.export', $this->roster->id))->assertOk()->assertDownload($this->roster->number().'.xlsx');
    }

    /** @param  list<list<mixed>>  $rows */
    private function excelFile(array $headings, array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('roster.xlsx', Excel::raw(new TemplateSheet('Roster', $headings, $rows), ExcelFormat::XLSX));
    }

    public function test_an_excel_file_is_imported(): void
    {
        RosterItem::factory()->for($this->roster)->create(['employee_id' => $this->karim->id, 'date' => '2026-12-07', 'shift_id' => $this->morning->id]);

        $file = $this->excelFile(['Code', 'Name', '2026-12-01', '2026-12-02', '2026-12-03'], [
            ['e-001', 'Karim', 'm', 'OFF', 'call'],     // codes are not case-sensitive
            ['E-002', 'Salma', 'N', '', 'N'],
        ]);

        Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])
            ->set('file', $file)
            ->call('import')
            ->assertSet('importProblems', [])
            ->assertDispatched('roster-cells');

        $grid = $this->grid();
        $this->assertSame([(string) $this->morning->id, 'off', 'call'], [$grid[$this->karim->id]['2026-12-01'], $grid[$this->karim->id]['2026-12-02'], $grid[$this->karim->id]['2026-12-03']]);
        $this->assertSame((string) $this->morning->id, $grid[$this->karim->id]['2026-12-07']);   // not in the file: kept
        $this->assertSame((string) $this->night->id, $grid[$this->salma->id]['2026-12-03']);
    }

    public function test_an_excel_file_with_problems_changes_nothing(): void
    {
        $file = $this->excelFile(['Code', 'Name', '2026-12-01', '2026-12-09', 'tomorrow'], [
            ['E-001', 'Karim', 'X', '', ''],     // unknown code
            ['E-003', 'Rafi', 'M', '', ''],      // other unit
            ['E-404', 'Nobody', 'M', '', ''],    // unknown employee
        ]);

        $component = Livewire::test(RosterGrid::class, ['rosterId' => $this->roster->id])
            ->set('file', $file)
            ->call('import')
            ->assertNotDispatched('roster-cells');

        $problems = implode("\n", $component->get('importProblems'));
        $this->assertStringContainsString('2026-12-09 is outside the roster period', $problems);
        $this->assertStringContainsString('"tomorrow" is not a date', $problems);
        $this->assertStringContainsString('"X" is not a shift code', $problems);
        $this->assertStringContainsString('"E-003" is not in this roster', $problems);
        $this->assertStringContainsString('"E-404" is not in this roster', $problems);
        $this->assertSame(0, RosterItem::count());
    }
}
