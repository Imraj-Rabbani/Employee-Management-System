<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Models\Activity;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\LeaveBridging;
use App\Modules\Hrm\Enums\LeaveEarnType;
use App\Modules\Hrm\Enums\LeaveEligibleFrom;
use App\Modules\Hrm\Livewire\Employees\JobForm;
use App\Modules\Hrm\Livewire\Leave\LeaveProfileForm;
use App\Modules\Hrm\Livewire\Leave\LeaveTypeForm;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveProfile;
use App\Modules\Hrm\Models\LeaveProfileItem;
use App\Modules\Hrm\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 8.1: HRM → Leave → Leave types / Leave profiles, and the leave profile on the employee's Job tab. [LV-01, LV-02]
 */
class LeaveSetupTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator
    }

    public function test_both_sections_open_and_need_the_permission(): void
    {
        $casual = LeaveType::factory()->create(['code' => 'CL', 'name' => 'Casual leave']);
        LeaveProfile::factory()->create(['name' => 'Staff'])->syncEntitlements([$casual->id => 10]);

        $this->get('/hrm/leave')->assertRedirect(route('hrm.leave.types'));   // first tab
        $this->get('/hrm/leave/types')->assertOk()->assertSee('Casual leave')->assertSee(route('hrm.leave.profiles'));
        $this->get('/hrm/leave/profiles')->assertOk()->assertSee('Staff')->assertSee('CL 10');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.leave.setup']);
        $this->get('/hrm/leave/types')->assertOk();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get('/hrm/leave/types')->assertForbidden();
        $this->get('/hrm/leave')->assertForbidden();
    }

    public function test_a_leave_type_is_created_with_its_rules_and_reopened(): void
    {
        Livewire::test(LeaveTypeForm::class)
            ->dispatch('create-leave-type')
            ->set('form.code', 'ML')
            ->set('form.name', 'Maternity leave')
            ->set('form.gender', (string) Gender::Female->value)
            ->set('form.eligible_after_months', '6')
            ->set('form.eligible_from', (string) LeaveEligibleFrom::ConfirmationDate->value)
            ->set('form.max_times_in_service', '2')
            ->set('form.max_days', '112')
            ->set('form.attachment_after_days', '0')            // 0 = always (not "empty")
            ->set('form.bridging', (string) LeaveBridging::TwoSided->value)
            ->set('form.can_carry_forward', true)
            ->set('form.carry_forward_limit', '15')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('leave-type-saved');

        $type = LeaveType::sole();
        $this->assertSame(Gender::Female, $type->gender);
        $this->assertNull($type->religion);                    // empty = everyone
        $this->assertSame(LeaveEligibleFrom::ConfirmationDate, $type->eligible_from);
        $this->assertSame(6, $type->eligible_after_months);
        $this->assertSame(2, $type->max_times_in_service);
        $this->assertNull($type->min_days);                    // empty = no minimum
        $this->assertSame(112.0, $type->max_days);
        $this->assertSame(0.0, $type->attachment_after_days);
        $this->assertSame(LeaveBridging::TwoSided, $type->bridging);
        $this->assertSame(LeaveEarnType::Fixed, $type->earn_type);
        $this->assertTrue($type->requires_balance);            // default on
        $this->assertSame(15.0, $type->carry_forward_limit);
        $this->assertNull($type->carry_forward_expiry_months);

        // Reopened: enum values come back as numbers (0 must not turn into "empty").
        Livewire::test(LeaveTypeForm::class)
            ->dispatch('edit-leave-type', id: $type->id)
            ->assertSet('form.gender', Gender::Female->value)
            ->assertSet('form.religion', '')
            ->assertSet('form.earn_type', LeaveEarnType::Fixed->value)
            ->assertSet('form.carry_forward_limit', 15.0)
            // Switching carry forward off clears its settings: an old hidden limit must not act as a rule.
            ->set('form.can_carry_forward', false)
            ->call('save')
            ->assertHasNoErrors();

        $type->refresh();
        $this->assertFalse($type->can_carry_forward);
        $this->assertNull($type->carry_forward_limit);
    }

    public function test_leave_type_rules(): void
    {
        LeaveType::factory()->create(['code' => 'CL']);

        Livewire::test(LeaveTypeForm::class)
            ->dispatch('create-leave-type')
            ->set('form.code', 'CL')                                     // taken
            ->set('form.name', 'Earned leave')
            ->set('form.earn_type', (string) LeaveEarnType::PerDaysWorked->value)   // needs "days worked"
            ->set('form.min_days', '0.3')                                // not a half-day step
            ->call('save')
            ->assertHasErrors(['form.code', 'form.earn_per_days_worked', 'form.min_days'])
            ->set('form.code', 'EL')
            ->set('form.earn_per_days_worked', '18')
            ->set('form.min_days', '3')
            ->set('form.max_days', '1.5')
            ->call('save')
            ->assertHasErrors(['form.max_days'])                         // below the minimum, and a half day
            ->set('form.max_days', '10.5')
            ->call('save')
            ->assertHasErrors(['form.max_days'])                         // half days are not allowed for this type
            ->set('form.allow_half_day', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(18.0, LeaveType::where('code', 'EL')->sole()->earn_per_days_worked);
    }

    public function test_days_worked_is_cleared_for_another_earning(): void
    {
        $type = LeaveType::factory()->create(['earn_type' => LeaveEarnType::PerDaysWorked, 'earn_per_days_worked' => 18]);

        Livewire::test(LeaveTypeForm::class)
            ->dispatch('edit-leave-type', id: $type->id)
            ->set('form.earn_type', (string) LeaveEarnType::MonthlyAccrual->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($type->fresh()->earn_per_days_worked);
    }

    public function test_a_leave_profile_holds_days_per_leave_type(): void
    {
        $casual = LeaveType::factory()->create(['code' => 'CL', 'sequence' => 1]);
        $sick = LeaveType::factory()->create(['code' => 'SL', 'sequence' => 2]);
        $lwp = LeaveType::factory()->create(['code' => 'LWP', 'sequence' => 3, 'is_lwp' => true, 'requires_balance' => false]);

        Livewire::test(LeaveProfileForm::class)
            ->dispatch('create-leave-profile')
            ->assertSee($casual->name)->assertSee($lwp->name)
            ->set('form.name', 'Staff')
            ->set("days.{$casual->id}", '10')
            ->set("days.{$sick->id}", '')                // empty = not in the profile
            ->set("days.{$lwp->id}", '0')                // 0 = in it, without days
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('leave-profile-saved');

        $profile = LeaveProfile::sole();
        $this->assertSame([$casual->id => 10.0, $lwp->id => 0.0], $profile->entitlements());

        Livewire::test(LeaveProfileForm::class)
            ->dispatch('edit-leave-profile', id: $profile->id)
            ->assertSet("days.{$casual->id}", 10.0)
            ->set("days.{$casual->id}", '12.5')
            ->set("days.{$sick->id}", '14')
            ->set("days.{$lwp->id}", '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$casual->id => 12.5, $sick->id => 14.0], $profile->entitlements());

        // Not a half-day step, or negative: refused, nothing changed.
        Livewire::test(LeaveProfileForm::class)
            ->dispatch('edit-leave-profile', id: $profile->id)
            ->set("days.{$casual->id}", '-1')
            ->set("days.{$sick->id}", '2.2')
            ->call('save')
            ->assertHasErrors(["days.{$casual->id}", "days.{$sick->id}"]);

        $this->assertSame([$casual->id => 12.5, $sick->id => 14.0], $profile->entitlements());
    }

    public function test_entitlement_changes_are_one_readable_history_entry(): void
    {
        $casual = LeaveType::factory()->create(['code' => 'CL']);
        $sick = LeaveType::factory()->create(['code' => 'SL']);
        $profile = LeaveProfile::factory()->create();
        $profile->syncEntitlements([$casual->id => 10]);

        $before = Activity::count();
        $profile->syncEntitlements([$casual->id => 12, $sick->id => 14]);

        $this->assertSame($before + 1, Activity::count(), 'One entry on the profile, not one per row.');
        $entry = Activity::latest('id')->firstOrFail();
        $this->assertSame($profile->id, $entry->subject_id);
        $this->assertSame('CL 10', $entry->attribute_changes?->get('old')['entitlements']);
        $this->assertSame('CL 12, SL 14', $entry->attribute_changes?->get('attributes')['entitlements']);
        $this->assertSame(0, Activity::where('subject_type', (new LeaveProfileItem)->getMorphClass())->count());

        // Saving the same days again writes nothing.
        $profile->syncEntitlements([$sick->id => 14, $casual->id => 12]);
        $this->assertSame($before + 1, Activity::count());
    }

    public function test_an_inactive_leave_type_already_in_a_profile_is_kept(): void
    {
        $old = LeaveType::factory()->create(['name' => 'Old leave', 'is_active' => false]);
        LeaveType::factory()->create(['name' => 'Retired leave', 'is_active' => false]);
        $profile = LeaveProfile::factory()->create();
        $profile->syncEntitlements([$old->id => 5]);

        Livewire::test(LeaveProfileForm::class)
            ->dispatch('edit-leave-profile', id: $profile->id)
            ->assertSee('Old leave')
            ->assertDontSee('Retired leave')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$old->id => 5.0], $profile->entitlements());
    }

    public function test_used_types_and_profiles_are_not_deleted(): void
    {
        $type = LeaveType::factory()->create();
        $profile = LeaveProfile::factory()->create();
        $profile->syncEntitlements([$type->id => 10]);
        $employee = Employee::factory()->create(['leave_profile_id' => $profile->id]);

        Livewire::test(LeaveTypeForm::class)
            ->dispatch('edit-leave-type', id: $type->id)
            ->call('delete')
            ->assertHasErrors(['form.code']);      // in a profile
        Livewire::test(LeaveProfileForm::class)
            ->dispatch('edit-leave-profile', id: $profile->id)
            ->call('delete')
            ->assertHasErrors(['form.name']);      // an employee has it
        $this->assertModelExists($type);
        $this->assertModelExists($profile);

        // Without employees the profile goes, and its days with it; then the type is free to go too.
        $employee->update(['leave_profile_id' => null]);
        Livewire::test(LeaveProfileForm::class)
            ->dispatch('edit-leave-profile', id: $profile->id)
            ->call('delete')
            ->assertHasNoErrors();
        $this->assertSame(0, LeaveProfileItem::count());
        Livewire::test(LeaveTypeForm::class)
            ->dispatch('edit-leave-type', id: $type->id)
            ->call('delete')
            ->assertHasNoErrors();
        $this->assertModelMissing($type);
    }

    public function test_the_leave_profile_is_assigned_on_the_job_tab(): void
    {
        $profile = LeaveProfile::factory()->create(['name' => 'Workers']);
        LeaveProfile::factory()->create(['name' => 'Closed', 'is_active' => false]);
        $employee = Employee::factory()->create();

        Livewire::test(JobForm::class, ['employee' => $employee])
            ->call('open')
            ->assertSee('Workers')
            ->assertDontSee('Closed')
            ->set('form.leave_profile_id', (string) $profile->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($profile->id, $employee->fresh()->leave_profile_id);
        $this->get(route('hrm.employees.show', ['employee' => $employee, 'tab' => 'job']))
            ->assertOk()->assertSee('Leave profile')->assertSee('Workers');
    }
}
