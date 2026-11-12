<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Livewire\Leave\EmployeeLeave;
use App\Modules\Hrm\Livewire\Leave\LeaveSettings;
use App\Modules\Hrm\Livewire\Leave\LeaveTypeForm;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Models\LeaveLedgerEntry;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\LeaveLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 8.2: the leave ledger, cached balances, carry forward / expiry, and Profile → Leave. [LV-03]
 */
class LeaveLedgerTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private LeaveLedger $ledger;

    private Employee $employee;

    private LeaveType $casual;

    private LeaveYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator
        $this->ledger = app(LeaveLedger::class);
        $this->employee = Employee::factory()->create();
        $this->casual = LeaveType::factory()->create(['code' => 'CL', 'name' => 'Casual leave']);
        $this->year = LeaveYear::of(2026);
    }

    public function test_credits_and_debits_keep_the_balance_equal_to_the_ledger(): void
    {
        $this->ledger->credit($this->employee, $this->casual, $this->year, 5, LeaveEntryType::Opening);
        $this->ledger->credit($this->employee, $this->casual, $this->year, 10, LeaveEntryType::Earned, CarbonImmutable::parse('2026-01-01'));
        $this->ledger->debit($this->employee, $this->casual, $this->year, 2.5, LeaveEntryType::Taken, CarbonImmutable::parse('2026-03-02'));
        $this->ledger->debit($this->employee, $this->casual, $this->year, 1, LeaveEntryType::Encashed);
        $this->ledger->adjust($this->employee, $this->casual, $this->year, -0.5, 'Counted twice in March');

        $balance = LeaveBalance::sole();
        $this->assertSame(5.0, $balance->opening);
        $this->assertSame(10.0, $balance->earned);
        $this->assertSame(2.5, $balance->taken);              // used amounts are positive
        $this->assertSame(1.0, $balance->encashed);
        $this->assertSame(-0.5, $balance->adjusted);
        $this->assertSame(11.0, $balance->balance);
        $this->assertSame(11.0, $this->ledger->balance($this->employee, $this->casual, $this->year));
        $this->assertSame(11.0, (float) LeaveLedgerEntry::sum('days'));

        $opening = LeaveLedgerEntry::where('entry_type', LeaveEntryType::Opening)->sole();
        $this->assertSame('2026-01-01', $opening->entry_date->toDateString());   // opening = start of the leave year
        $this->assertSame(-2.5, LeaveLedgerEntry::where('entry_type', LeaveEntryType::Taken)->sole()->days);
    }

    public function test_entries_that_make_no_sense_are_refused(): void
    {
        $refused = 0;
        foreach ([
            fn () => $this->ledger->credit($this->employee, $this->casual, $this->year, -1, LeaveEntryType::Earned),
            fn () => $this->ledger->credit($this->employee, $this->casual, $this->year, 1, LeaveEntryType::Taken),
            fn () => $this->ledger->debit($this->employee, $this->casual, $this->year, 1, LeaveEntryType::Earned),
            fn () => $this->ledger->debit($this->employee, $this->casual, $this->year, 0, LeaveEntryType::Taken),
            fn () => $this->ledger->adjust($this->employee, $this->casual, $this->year, 2, '  '),
            fn () => $this->ledger->adjust($this->employee, $this->casual, $this->year, 0, 'Nothing'),
        ] as $call) {
            try {
                $call();
            } catch (InvalidArgumentException) {
                $refused++;
            }
        }

        $this->assertSame(6, $refused);
        $this->assertSame(0, LeaveLedgerEntry::count());
    }

    public function test_reverse_undoes_what_a_source_wrote_once(): void
    {
        $source = LeaveProfile::factory()->create();   // any model will do; 8.4 uses the leave application
        $this->ledger->credit($this->employee, $this->casual, $this->year, 10, LeaveEntryType::Earned);
        $this->ledger->debit($this->employee, $this->casual, $this->year, 2, LeaveEntryType::Taken, source: $source);
        $this->ledger->debit($this->employee, $this->casual, $this->year, 1, LeaveEntryType::Taken, source: $source);

        $this->assertSame(1, $this->ledger->reverse($source, 'Withdrawn'));
        $this->assertSame(10.0, $this->ledger->balance($this->employee, $this->casual, $this->year));
        $this->assertSame(0.0, LeaveBalance::sole()->taken);
        $reversal = LeaveLedgerEntry::latest('id')->firstOrFail();
        $this->assertSame(3.0, $reversal->days);
        $this->assertSame(LeaveEntryType::Taken, $reversal->entry_type);
        $this->assertSame('Withdrawn', $reversal->note);

        $this->assertSame(0, $this->ledger->reverse($source), 'Nothing left to undo.');
    }

    public function test_year_end_carries_forward_up_to_the_limit_and_the_rest_expires(): void
    {
        $this->casual->update(['can_carry_forward' => true, 'carry_forward_limit' => 5, 'carry_forward_expiry_months' => 3]);
        $this->ledger->credit($this->employee, $this->casual, $this->year, 10, LeaveEntryType::Earned);
        $this->ledger->debit($this->employee, $this->casual, $this->year, 2, LeaveEntryType::Taken);

        $this->assertSame(5.0, $this->ledger->closeYear($this->employee, $this->casual, $this->year));

        $old = LeaveBalance::where('year', 2026)->sole();
        $this->assertSame(-5.0, $old->carried_forward);
        $this->assertSame(3.0, $old->expired);
        $this->assertSame(0.0, $old->balance);

        $new = LeaveBalance::where('year', 2027)->sole();
        $this->assertSame(5.0, $new->carried_forward);
        $this->assertSame(5.0, $new->balance);
        $carried = LeaveLedgerEntry::where('year', 2027)->sole();
        $this->assertSame('2027-01-01', $carried->entry_date->toDateString());
        $this->assertSame('2027-04-01', $carried->expires_on->toDateString());

        // Once per year.
        $this->assertSame(0.0, $this->ledger->closeYear($this->employee, $this->casual, $this->year));
        $this->assertSame(5, LeaveLedgerEntry::count());   // earned, taken, carried out, carried in, expired: nothing new
    }

    public function test_year_end_without_carry_forward_lapses_everything(): void
    {
        $this->ledger->credit($this->employee, $this->casual, $this->year, 4, LeaveEntryType::Earned);

        $this->assertSame(0.0, $this->ledger->closeYear($this->employee, $this->casual, $this->year));
        $this->assertSame(0.0, $this->ledger->balance($this->employee, $this->casual, $this->year));
        $this->assertSame(4.0, LeaveBalance::sole()->expired);
        $this->assertSame(0, LeaveBalance::where('year', 2027)->count());
    }

    public function test_carried_days_are_used_first_and_only_the_rest_expires(): void
    {
        $this->casual->update(['can_carry_forward' => true, 'carry_forward_expiry_months' => 3]);
        $this->ledger->credit($this->employee, $this->casual, $this->year, 6, LeaveEntryType::Earned);
        $this->ledger->closeYear($this->employee, $this->casual, $this->year);   // 6 carried into 2027, valid until 31 March
        $next = $this->year->next();
        $this->ledger->credit($this->employee, $this->casual, $next, 10, LeaveEntryType::Earned, CarbonImmutable::parse('2027-01-01'));
        $this->ledger->debit($this->employee, $this->casual, $next, 4, LeaveEntryType::Taken, CarbonImmutable::parse('2027-02-10'));
        $this->ledger->debit($this->employee, $this->casual, $next, 3, LeaveEntryType::Taken, CarbonImmutable::parse('2027-05-10'));   // after expiry: not from the carried days

        $this->assertSame(0, $this->ledger->expireCarriedForward(CarbonImmutable::parse('2027-03-31')), 'Still valid on 31 March.');
        $this->assertSame(1, $this->ledger->expireCarriedForward(CarbonImmutable::parse('2027-04-01')));

        // 6 carried − 4 taken before 1 April = 2 expire. 6 + 10 − 4 − 3 − 2 = 7 left.
        $this->assertSame(2.0, LeaveBalance::where('year', 2027)->sole()->expired);
        $this->assertSame(7.0, $this->ledger->balance($this->employee, $this->casual, $next));
        $this->assertSame(0, $this->ledger->expireCarriedForward(CarbonImmutable::parse('2027-04-02')), 'Done once.');
    }

    public function test_carried_days_all_used_writes_a_zero_row(): void
    {
        $this->casual->update(['can_carry_forward' => true, 'carry_forward_expiry_months' => 1]);
        $this->ledger->credit($this->employee, $this->casual, $this->year, 2, LeaveEntryType::Earned);
        $this->ledger->closeYear($this->employee, $this->casual, $this->year);
        $next = $this->year->next();
        $this->ledger->debit($this->employee, $this->casual, $next, 2, LeaveEntryType::Taken, CarbonImmutable::parse('2027-01-15'));

        $this->assertSame(1, $this->ledger->expireCarriedForward(CarbonImmutable::parse('2027-02-01')));
        $row = LeaveLedgerEntry::where('entry_type', LeaveEntryType::Expired)->sole();
        $this->assertSame(0.0, $row->days);
        $this->assertSame(0.0, $this->ledger->balance($this->employee, $this->casual, $next));
    }

    public function test_the_leave_tab_shows_balances_and_their_rows(): void
    {
        $profile = LeaveProfile::factory()->create(['name' => 'Staff']);
        $profile->syncEntitlements([$this->casual->id => 10]);
        $this->employee->update(['leave_profile_id' => $profile->id]);
        $year = LeaveYear::current();
        $this->ledger->credit($this->employee, $this->casual, $year, 7.5, LeaveEntryType::Opening, note: 'From the old system');

        $this->get(route('hrm.employees.show', ['employee' => $this->employee, 'tab' => 'leave']))
            ->assertOk()->assertSee('Leave profile: Staff')->assertSee('Casual leave (CL)')->assertSee('7.5');

        Livewire::test(EmployeeLeave::class, ['employee' => $this->employee])
            ->call('show', $this->casual->id)
            ->assertSee('From the old system')
            ->assertSee('Opening balance');
    }

    public function test_hr_adds_an_opening_balance_and_a_correction(): void
    {
        $year = LeaveYear::current();

        $component = Livewire::test(EmployeeLeave::class, ['employee' => $this->employee])
            ->call('open', $this->casual->id)
            ->assertSet('form.leave_type_id', (string) $this->casual->id)
            ->set('form.days', '-2')                              // an opening balance cannot be negative
            ->call('save')
            ->assertHasErrors(['form.days'])
            ->set('form.days', '8')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(8.0, $this->ledger->balance($this->employee, $this->casual, $year));

        // A second opening balance in the same year is refused: corrections change it.
        $component->call('open', $this->casual->id)
            ->set('form.days', '3')
            ->call('save')
            ->assertHasErrors(['form.kind'])
            ->set('form.kind', 'adjustment')
            ->set('form.days', '-1.5')
            ->call('save')
            ->assertHasErrors(['form.note'])                      // a correction needs a reason
            ->set('form.note', 'Counted twice')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(6.5, $this->ledger->balance($this->employee, $this->casual, $year));
        $this->assertSame(auth()->id(), LeaveLedgerEntry::latest('id')->firstOrFail()->created_by_user_id);
    }

    public function test_entering_balances_needs_its_permission(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);

        Livewire::test(EmployeeLeave::class, ['employee' => $this->employee])
            ->assertOk()
            ->assertDontSee('Add entry')
            ->call('open')
            ->assertForbidden();
    }

    public function test_the_leave_year_setting_is_fixed_once_balances_exist(): void
    {
        Livewire::test(LeaveSettings::class)
            ->set('startMonth', 7)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(7, LeaveYear::startMonth());
        $this->assertSame('2026-07-01', LeaveYear::containing('2027-02-01')->start()->toDateString());

        $this->ledger->credit($this->employee, $this->casual, LeaveYear::of(2026), 1, LeaveEntryType::Earned);

        Livewire::test(LeaveSettings::class)
            ->set('startMonth', 1)
            ->call('save')
            ->assertHasErrors(['startMonth']);
        $this->assertSame(7, LeaveYear::startMonth());
    }

    public function test_a_leave_type_with_entries_cannot_be_deleted(): void
    {
        $this->ledger->credit($this->employee, $this->casual, $this->year, 1, LeaveEntryType::Earned);

        Livewire::test(LeaveTypeForm::class)
            ->dispatch('edit-leave-type', id: $this->casual->id)
            ->call('delete')
            ->assertHasErrors(['form.code']);
        $this->assertModelExists($this->casual);
    }
}
