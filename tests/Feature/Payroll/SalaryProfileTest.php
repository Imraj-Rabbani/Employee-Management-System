<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Money;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Enums\CalcMethod;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\ProfileValueType;
use App\Modules\Payroll\Enums\SalaryBasis;
use App\Modules\Payroll\Exceptions\SalaryProfileException;
use App\Modules\Payroll\Livewire\SalaryProfileForm;
use App\Modules\Payroll\Livewire\SalaryProfileTable;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Models\SalaryProfile;
use App\Modules\Payroll\Services\SalaryProfileCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 9.3: salary profiles (per grade) with head values, and "apply profile": basic / gross → head amounts. [PC-02]
 */
class SalaryProfileTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private SalaryHead $basic;

    private SalaryHead $houseRent;

    private SalaryHead $medical;

    private SalaryHead $conveyance;

    private SalaryHead $food;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();

        $this->basic = SalaryHead::factory()->create(['code' => 'BASIC', 'name' => 'Basic', 'is_basic' => true, 'amount' => null, 'sequence' => 1]);
        $this->houseRent = SalaryHead::factory()->percentOfBasic('50')->create(['code' => 'HR', 'name' => 'House rent', 'sequence' => 2]);
        $this->medical = SalaryHead::factory()->create(['code' => 'MED', 'name' => 'Medical', 'amount' => '750', 'sequence' => 3]);
        $this->conveyance = SalaryHead::factory()->create(['code' => 'CONV', 'name' => 'Conveyance', 'amount' => '450', 'sequence' => 4]);
        $this->food = SalaryHead::factory()->create(['code' => 'FOOD', 'name' => 'Food', 'amount' => '1250', 'sequence' => 5]);
    }

    /** Bangladesh labour-law style: HR 50% of basic + 2,450 fixed. */
    private function labourLawProfile(): SalaryProfile
    {
        $profile = SalaryProfile::factory()->create(['code' => 'G5', 'name' => 'Grade 5']);
        $profile->syncItems([
            $this->houseRent->id => ['value_type' => 'percent_basic', 'value' => '50'],
            $this->medical->id => ['value_type' => 'amount', 'value' => '750'],
            $this->conveyance->id => ['value_type' => 'amount', 'value' => '450'],
            $this->food->id => ['value_type' => 'amount', 'value' => '1250'],
        ]);

        return $profile;
    }

    private function apply(SalaryProfile $profile, SalaryBasis $basis, string $amount): SalaryBreakdown
    {
        return app(SalaryProfileCalculator::class)->forProfile($profile, $basis, Money::of($amount));
    }

    public function test_from_gross_the_heads_add_up_to_exactly_the_gross(): void
    {
        $result = $this->apply($this->labourLawProfile(), SalaryBasis::Gross, '30000');

        // basic = (30,000 − 2,450) / 1.5 = 18,366.67 → HR 9,183.34 → one cent too much, so the basic gives it back.
        $amounts = array_map(fn (Money $m) => $m->toDecimal(), $result->amounts());
        $this->assertSame('18366.66', $amounts[$this->basic->id]);
        $this->assertSame('9183.34', $amounts[$this->houseRent->id]);
        $this->assertSame('750.00', $amounts[$this->medical->id]);
        $this->assertSame('30000.00', $result->gross->toDecimal());
        $this->assertSame('30000.00', $result->net->toDecimal());
        $this->assertSame(['BASIC', 'HR', 'MED', 'CONV', 'FOOD'], array_map(fn ($l) => $l->head->code, $result->lines));
    }

    public function test_from_basic_the_gross_is_worked_out(): void
    {
        $result = $this->apply($this->labourLawProfile(), SalaryBasis::Basic, '20000');

        $this->assertSame('20000.00', $result->basic->toDecimal());
        $this->assertSame('10000.00', $result->amounts()[$this->houseRent->id]->toDecimal());
        $this->assertSame('32450.00', $result->gross->toDecimal());   // 20,000 + 10,000 + 2,450
    }

    public function test_percent_of_gross_earnings_and_deductions(): void
    {
        $pf = SalaryHead::factory()->create(['code' => 'PF', 'name' => 'Provident fund', 'type' => HeadType::Deduction, 'in_gross' => false, 'sequence' => 9]);
        $employerPf = SalaryHead::factory()->create(['code' => 'EPF', 'name' => 'Employer PF', 'type' => HeadType::Info, 'in_gross' => false, 'sequence' => 10]);
        $profile = SalaryProfile::factory()->create();
        $profile->syncItems([
            $this->houseRent->id => ['value_type' => 'percent_gross', 'value' => '30'],
            $this->medical->id => ['value_type' => 'percent_gross', 'value' => '10'],
            $pf->id => ['value_type' => 'percent_basic', 'value' => '10'],
            $employerPf->id => ['value_type' => 'percent_basic', 'value' => '10'],
        ]);

        // basic = 50,000 × (1 − 0.4) = 30,000
        $result = $this->apply($profile, SalaryBasis::Gross, '50000');
        $this->assertSame('30000.00', $result->basic->toDecimal());
        $this->assertSame('15000.00', $result->amounts()[$this->houseRent->id]->toDecimal());
        $this->assertSame('3000.00', $result->deductions->toDecimal());           // PF only; Employer PF is info
        $this->assertSame('47000.00', $result->net->toDecimal());

        // And back: from that basic the same gross.
        $this->assertSame('50000.00', $this->apply($profile, SalaryBasis::Basic, '30000')->gross->toDecimal());
    }

    public function test_problems_are_explained(): void
    {
        $profile = $this->labourLawProfile();

        try {
            $this->apply($profile, SalaryBasis::Gross, '2000');   // less than the 2,450 fixed
            $this->fail('No exception');
        } catch (SalaryProfileException $e) {
            $this->assertStringContainsString('2,450.00', $e->getMessage());
        }

        $this->basic->update(['is_basic' => false]);
        $this->expectException(SalaryProfileException::class);
        $this->apply($profile, SalaryBasis::Gross, '30000');
    }

    public function test_the_form_saves_only_filled_heads_with_one_history_entry(): void
    {
        $grade = Grade::factory()->create();

        Livewire::test(SalaryProfileForm::class)
            ->dispatch('create-salary-profile')
            ->assertSet("items.{$this->houseRent->id}.value_type", 'percent_basic')   // from the head's own rule
            ->assertSet("items.{$this->medical->id}.value_type", 'amount')
            ->set('form.code', 'G5')->set('form.name', 'Grade 5')->set('form.grade_id', $grade->id)
            ->set("items.{$this->houseRent->id}.value", '50')
            ->set("items.{$this->medical->id}.value", '750')
            ->call('save')
            ->assertHasNoErrors();

        $profile = SalaryProfile::sole();
        $this->assertSame($grade->id, $profile->grade_id);
        $this->assertSame([
            $this->houseRent->id => ['value_type' => 'percent_basic', 'value' => '50.0000'],
            $this->medical->id => ['value_type' => 'amount', 'value' => '750.00'],
        ], $profile->values());
        $this->assertSame('HR 50% of basic · MED 750.00', $profile->summary());
        $this->assertDatabaseHas('activity_log', ['subject_id' => $profile->id, 'subject_type' => $profile->getMorphClass(), 'event' => 'updated'], 'tenant');
    }

    public function test_editing_loads_values_and_an_emptied_head_is_removed(): void
    {
        $profile = $this->labourLawProfile();

        Livewire::test(SalaryProfileForm::class)
            ->dispatch('edit-salary-profile', id: $profile->id)
            ->assertSet("items.{$this->houseRent->id}.value", '50.0000')
            ->set("items.{$this->food->id}.value", '')
            ->set("items.{$this->houseRent->id}.value_type", 'percent_gross')
            ->set("items.{$this->houseRent->id}.value", '25')
            ->call('save')->assertHasNoErrors();

        $values = $profile->fresh()->values();
        $this->assertArrayNotHasKey($this->food->id, $values);
        $this->assertSame(['value_type' => 'percent_gross', 'value' => '25.0000'], $values[$this->houseRent->id]);
    }

    public function test_percent_of_gross_earnings_must_leave_room_for_the_basic(): void
    {
        Livewire::test(SalaryProfileForm::class)
            ->dispatch('create-salary-profile')
            ->set('form.code', 'X')->set('form.name', 'X')
            ->set("items.{$this->houseRent->id}.value_type", 'percent_gross')->set("items.{$this->houseRent->id}.value", '60')
            ->set("items.{$this->medical->id}.value_type", 'percent_gross')->set("items.{$this->medical->id}.value", '40')
            ->call('save')
            ->assertHasErrors('items');

        $this->assertSame(0, SalaryProfile::count());
    }

    public function test_basic_formula_and_attendance_heads_are_not_offered(): void
    {
        $formula = SalaryHead::factory()->create(['code' => 'F', 'calc_method' => CalcMethod::Formula, 'formula' => 'BASIC * 2']);
        $perDay = SalaryHead::factory()->create(['code' => 'D', 'calc_method' => CalcMethod::Attendance]);

        $form = Livewire::test(SalaryProfileForm::class)->dispatch('create-salary-profile');
        $items = $form->get('items');

        $this->assertArrayHasKey($this->houseRent->id, $items);
        $this->assertArrayNotHasKey($this->basic->id, $items);
        $this->assertArrayNotHasKey($formula->id, $items);
        $this->assertArrayNotHasKey($perDay->id, $items);

        // Even when the browser sends them anyway.
        $form->set('form.code', 'X')->set('form.name', 'X')
            ->set("items.{$formula->id}", ['value_type' => 'amount', 'value' => '5'])
            ->call('save')->assertHasNoErrors();
        $this->assertSame([], SalaryProfile::sole()->values());
    }

    public function test_the_form_previews_the_applied_profile(): void
    {
        $profile = $this->labourLawProfile();

        Livewire::test(SalaryProfileForm::class)
            ->dispatch('edit-salary-profile', id: $profile->id)
            ->set('previewBasis', 'gross')->set('previewAmount', '30000')
            ->assertSeeHtml('18,366.66')->assertSeeHtml('30,000.00')
            ->set("items.{$this->food->id}.value", '')   // unsaved change shows at once
            ->assertSeeHtml('19,200.00')   // (30,000 − 1,200) / 1.5
            ->set('previewAmount', '1')
            ->assertSee('too small');
    }

    public function test_a_profile_in_use_cannot_be_deleted(): void
    {
        $profile = $this->labourLawProfile();
        Employee::factory()->create(['salary_profile_id' => $profile->id]);

        Livewire::test(SalaryProfileForm::class)->dispatch('edit-salary-profile', id: $profile->id)
            ->call('delete')->assertHasErrors('form.code');
        $this->assertTrue($profile->exists());

        $unused = SalaryProfile::factory()->create();
        $unused->syncItems([$this->medical->id => ['value_type' => 'amount', 'value' => '1']]);
        Livewire::test(SalaryProfileForm::class)->dispatch('edit-salary-profile', id: $unused->id)->call('delete');
        $this->assertModelMissing($unused);
        $this->assertDatabaseMissing('salary_profile_items', ['salary_profile_id' => $unused->id], 'tenant');
    }

    public function test_the_list_and_the_page(): void
    {
        $this->labourLawProfile();

        Livewire::test(SalaryProfileTable::class)->assertSee('Grade 5')->assertSee('HR 50% of basic · MED 750.00');
        $this->get('/payroll/setup/profiles')->assertOk()->assertSee('Salary profiles')->assertSee('New salary profile');
    }

    public function test_value_type_follows_the_head_rule(): void
    {
        $this->assertSame(ProfileValueType::PercentOfGross, ProfileValueType::fromCalcMethod(CalcMethod::PercentOfGross));
        $this->assertSame(ProfileValueType::Amount, ProfileValueType::fromCalcMethod(CalcMethod::Fixed));
    }
}
