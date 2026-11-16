<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Money;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Livewire\SalaryHeadForm;
use App\Modules\Payroll\Livewire\SalaryHeadTable;
use App\Modules\Payroll\Models\SalaryHead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 9.2: salary heads (type, calculation, tax, cycle ...) with CRUD and a rule preview. [PC-01]
 */
class SalaryHeadTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();
    }

    public function test_a_percent_of_basic_head_is_saved_with_its_enums_and_rule(): void
    {
        Livewire::test(SalaryHeadForm::class)
            ->dispatch('create-salary-head')
            ->set('form.code', 'HRENT')
            ->set('form.name', 'House rent')
            ->set('form.calc_method', 'percent_basic')
            ->set('form.rate', '50')
            ->set('form.sequence', 2)
            ->call('save')
            ->assertHasNoErrors();

        $head = SalaryHead::sole();
        $this->assertSame(HeadType::Earning, $head->type);
        $this->assertSame(CalcMethod::PercentOfBasic, $head->calc_method);
        $this->assertSame('50.0000', $head->rate);
        $this->assertNull($head->amount);
        $this->assertSame('50% of basic', $head->rule());
    }

    public function test_a_fixed_head_keeps_its_amount_as_money(): void
    {
        Livewire::test(SalaryHeadForm::class)
            ->dispatch('create-salary-head')
            ->set('form.code', 'CONV')->set('form.name', 'Conveyance')
            ->set('form.amount', '2500.5')
            ->call('save')
            ->assertHasNoErrors();

        $head = SalaryHead::sole();
        $this->assertInstanceOf(Money::class, $head->amount);
        $this->assertSame('2500.50', $head->amount->toDecimal());
        $this->assertSame('2500.50', $head->getRawOriginal('amount'));
    }

    public function test_the_field_for_the_chosen_calculation_is_required(): void
    {
        $form = Livewire::test(SalaryHeadForm::class)->dispatch('create-salary-head')
            ->set('form.code', 'X')->set('form.name', 'X head');

        $form->set('form.calc_method', 'fixed')->call('save')->assertHasErrors('form.amount');
        $form->set('form.calc_method', 'percent_gross')->call('save')->assertHasErrors('form.rate');
        $form->set('form.calc_method', 'formula')->call('save')->assertHasErrors('form.formula');
        $this->assertSame(0, SalaryHead::count());
    }

    public function test_code_and_name_must_be_unique_and_code_must_be_a_plain_word(): void
    {
        SalaryHead::factory()->create(['code' => 'BASIC', 'name' => 'Basic']);

        Livewire::test(SalaryHeadForm::class)->dispatch('create-salary-head')
            ->set('form.code', 'BASIC')->set('form.name', 'Basic')->set('form.amount', '1')
            ->call('save')->assertHasErrors(['form.code', 'form.name']);

        Livewire::test(SalaryHeadForm::class)->dispatch('create-salary-head')
            ->set('form.code', 'two words')->set('form.name', 'Other')->set('form.amount', '1')
            ->call('save')->assertHasErrors('form.code');
    }

    public function test_a_head_can_be_edited_and_deleted(): void
    {
        $head = SalaryHead::factory()->percentOfBasic('40')->create(['code' => 'MED', 'name' => 'Medical']);

        Livewire::test(SalaryHeadForm::class)
            ->dispatch('edit-salary-head', id: $head->id)
            ->assertSet('form.rate', '40.0000')
            ->set('form.rate', '45')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('45.0000', $head->fresh()->rate);

        Livewire::test(SalaryHeadForm::class)->dispatch('edit-salary-head', id: $head->id)->call('delete');
        $this->assertSame(0, SalaryHead::count());
    }

    public function test_changes_are_written_to_the_activity_log(): void
    {
        $head = SalaryHead::factory()->create(['amount' => '1000']);
        $head->update(['amount' => '1500']);

        $this->assertDatabaseHas('activity_log', ['subject_id' => $head->id, 'event' => 'updated'], 'tenant');
    }

    public function test_preview_shows_what_the_rule_comes_to(): void
    {
        $form = Livewire::test(SalaryHeadForm::class)
            ->dispatch('create-salary-head')
            ->set('form.calc_method', 'percent_basic')
            ->set('form.rate', '45.5');   // sample basic 50,000

        $form->assertSeeHtml('22,750.00')->assertSee('45.5% of basic');

        $form->set('sampleBasic', '60000')->assertSeeHtml('27,300.00');
        $form->set('form.calc_method', 'percent_gross')->set('form.rate', '10')->assertSee('10% of gross')->assertSeeHtml('8,000.00');
        $form->set('form.calc_method', 'attendance')->set('form.amount', '100')->assertSeeHtml('2,600.00');   // 26 days
        $form->set('form.calc_method', 'formula')->set('form.formula', 'BASIC * 0.5')->assertSee('payroll run');
    }

    public function test_preview_survives_half_typed_input(): void
    {
        Livewire::test(SalaryHeadForm::class)->dispatch('create-salary-head')
            ->set('form.calc_method', 'percent_basic')->set('form.rate', '4.')
            ->set('sampleBasic', '')
            ->assertOk();
    }

    public function test_taxable_part_uses_percent_and_exemption_cap(): void
    {
        $head = SalaryHead::factory()->make(['taxable_percent' => '100', 'tax_exemption_cap' => '5000']);

        $this->assertSame('5000.00', $head->taxablePart(Money::of(10000))->toDecimal());
        $this->assertSame('0.00', $head->taxablePart(Money::of(3000))->toDecimal());   // never below zero

        $half = SalaryHead::factory()->make(['taxable_percent' => '50']);
        $this->assertSame('5000.00', $half->taxablePart(Money::of(10000))->toDecimal());
    }

    public function test_the_list_shows_heads_in_order(): void
    {
        SalaryHead::factory()->create(['name' => 'Zeta head', 'sequence' => 1]);
        SalaryHead::factory()->percentOfBasic('50')->create(['name' => 'Alpha head', 'sequence' => 2]);

        Livewire::test(SalaryHeadTable::class)->assertSeeInOrder(['Zeta head', 'Alpha head', '50% of basic']);
    }

    public function test_one_head_is_the_basic_and_needs_no_amount(): void
    {
        Livewire::test(SalaryHeadForm::class)->dispatch('create-salary-head')
            ->set('form.code', 'BASIC')->set('form.name', 'Basic')
            ->set('form.is_basic', true)            // fixed method, but no amount needed
            ->assertSee('Basic salary (set per employee)')
            ->call('save')->assertHasNoErrors();

        $this->assertTrue(SalaryHead::basic()->is(SalaryHead::sole()));

        // A second basic head is refused, and so is a basic that is a deduction.
        Livewire::test(SalaryHeadForm::class)->dispatch('create-salary-head')
            ->set('form.code', 'B2')->set('form.name', 'Basic 2')->set('form.is_basic', true)
            ->call('save')->assertHasErrors('form.is_basic');

        $basic = SalaryHead::sole();
        Livewire::test(SalaryHeadForm::class)->dispatch('edit-salary-head', id: $basic->id)
            ->set('form.type', 'deduction')
            ->call('save')->assertHasErrors('form.is_basic');
    }

    public function test_the_page_needs_the_manage_permission(): void
    {
        $this->get('/payroll/setup')->assertRedirect(route('payroll.setup.heads'));
        $this->get('/payroll/setup/heads')->assertOk()->assertSee('Salary heads')->assertSee('New salary head');
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $this->actingAsTenantUser(permissions: ['payroll.salary.view']);

        $this->get('/payroll/setup/heads')->assertForbidden();
    }
}
