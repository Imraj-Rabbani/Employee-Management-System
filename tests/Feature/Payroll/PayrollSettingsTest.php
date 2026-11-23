<?php

namespace Tests\Feature\Payroll;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\HeadRole;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Livewire\EmployeeSalaryTab;
use App\Modules\Payroll\Livewire\PayrollSettingsForm;
use App\Modules\Payroll\Livewire\PayScaleProfileForm;
use App\Modules\Payroll\Livewire\PayScaleProfileTable;
use App\Modules\Payroll\Livewire\SalaryHeadForm;
use App\Modules\Payroll\Models\PayScaleProfile;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Services\PayrollSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Pay scale profiles and the payroll settings screen (head mapping, default pay scale).
 */
class PayrollSettingsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();
    }

    public function test_a_pay_scale_profile_is_saved_and_listed(): void
    {
        Livewire::test(PayScaleProfileForm::class)
            ->dispatch('create-pay-scale-profile')
            ->set('form.code', 'REG')->set('form.name', 'Regular staff')
            ->set('form.threshold_day', 15)
            ->set('form.ignore_late_lwp', true)
            ->call('save')->assertHasNoErrors();

        $profile = PayScaleProfile::sole();
        $this->assertTrue($profile->pays_arrear);
        $this->assertSame(15, $profile->threshold_day);
        $this->assertSame('Arrears paid · threshold day 15 · LWP deducted (not from lates) · absence deducted', $profile->summary());

        Livewire::test(PayScaleProfileTable::class)->assertSee('Regular staff')->assertSee('threshold day 15');
    }

    public function test_threshold_day_is_at_most_28_and_late_lwp_goes_with_lwp(): void
    {
        $form = Livewire::test(PayScaleProfileForm::class)->dispatch('create-pay-scale-profile')
            ->set('form.code', 'W')->set('form.name', 'Workers')
            ->set('form.threshold_day', 30)
            ->call('save')->assertHasErrors('form.threshold_day');

        $form->set('form.threshold_day', 0)
            ->set('form.ignore_late_lwp', true)->set('form.deduct_lwp', false)
            ->call('save')->assertHasNoErrors();

        $this->assertFalse(PayScaleProfile::sole()->ignore_late_lwp);
    }

    public function test_a_pay_scale_in_use_cannot_be_deleted(): void
    {
        $profile = PayScaleProfile::factory()->create();
        Employee::factory()->create(['pay_scale_profile_id' => $profile->id]);

        Livewire::test(PayScaleProfileForm::class)->dispatch('edit-pay-scale-profile', id: $profile->id)
            ->call('delete')->assertHasErrors('form.code');
        $this->assertModelExists($profile);
    }

    public function test_heads_are_mapped_to_payroll_jobs(): void
    {
        $tax = SalaryHead::factory()->create(['code' => 'TAX', 'type' => HeadType::Deduction, 'in_gross' => false]);
        $ot = SalaryHead::factory()->create(['code' => 'OT']);
        $default = PayScaleProfile::factory()->create(['name' => 'Regular']);

        Livewire::test(PayrollSettingsForm::class)
            ->set('heads.tax', (string) $tax->id)
            ->set('heads.overtime', (string) $ot->id)
            ->set('defaultPayScale', (string) $default->id)
            ->call('save')->assertHasNoErrors();

        $settings = app(PayrollSettings::class);
        $this->assertTrue($settings->head(HeadRole::Tax)->is($tax));
        $this->assertTrue($settings->head(HeadRole::Overtime)->is($ot));
        $this->assertNull($settings->head(HeadRole::Lfa));
        $this->assertTrue($settings->payScaleFor(Employee::factory()->create())->is($default));   // no own profile → default

        // Reopened: the choices are filled in.
        Livewire::test(PayrollSettingsForm::class)->assertSet('heads.tax', (string) $tax->id)->assertSet('defaultPayScale', (string) $default->id);
    }

    public function test_a_head_must_fit_the_job_and_do_only_one(): void
    {
        $earning = SalaryHead::factory()->create();
        $basic = SalaryHead::factory()->create(['is_basic' => true]);

        Livewire::test(PayrollSettingsForm::class)
            ->set('heads.tax', (string) $earning->id)              // tax needs a deduction
            ->call('save')->assertHasErrors('heads.tax')
            ->set('heads.tax', '')
            ->set('heads.overtime', (string) $earning->id)->set('heads.lfa', (string) $earning->id)
            ->call('save')->assertHasErrors(['heads.overtime', 'heads.lfa'])
            ->set('heads.lfa', (string) $basic->id)
            ->call('save')->assertHasErrors('heads.lfa');

        $this->assertSame([], app(PayrollSettings::class)->headIds());
    }

    public function test_a_mapped_head_cannot_be_deleted(): void
    {
        $ot = SalaryHead::factory()->create(['code' => 'OT']);
        settings()->set(PayrollSettings::HEADS, ['overtime' => $ot->id]);

        Livewire::test(SalaryHeadForm::class)->dispatch('edit-salary-head', id: $ot->id)
            ->call('delete')->assertHasErrors('form.code');
        $this->assertModelExists($ot);
    }

    public function test_the_employee_gets_a_pay_scale_on_the_salary_tab(): void
    {
        $employee = Employee::factory()->create();
        $default = PayScaleProfile::factory()->create(['name' => 'Regular']);
        $workers = PayScaleProfile::factory()->create(['name' => 'Workers']);
        settings()->set(PayrollSettings::DEFAULT_PAY_SCALE, $default->id);

        Livewire::test(EmployeeSalaryTab::class, ['employee' => $employee])
            ->assertSee('company default')
            ->call('openPayScale')->set('payScaleId', (string) $workers->id)->call('savePayScale')
            ->assertSee('Workers');

        $this->assertSame($workers->id, $employee->fresh()->pay_scale_profile_id);
        $this->assertTrue(app(PayrollSettings::class)->payScaleFor($employee->fresh())->is($workers));
    }

    public function test_the_pages(): void
    {
        $this->get('/payroll/setup/pay-scales')->assertOk()->assertSee('New pay scale profile');
        $this->get('/payroll/setup/settings')->assertOk()->assertSee('Heads for payroll jobs')->assertSee('Income tax');

        $this->actingAsTenantUser(permissions: ['payroll.salary.view']);
        $this->get('/payroll/setup/settings')->assertForbidden();
    }
}
