<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Hrm\Enums\EmployeeRelation;
use App\Modules\Hrm\Livewire\Employees\FamilyMembers;
use App\Modules\Hrm\Livewire\Employees\Nominees;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeFamilyMember;
use App\Modules\Hrm\Models\EmployeeNominee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.5 (part 1): Family & Nominees tab. [EMP-02]
 */
class FamilyAndNomineesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Employee $rahim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;
        $this->rahim = Employee::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Uddin']);
    }

    private function addNominee(string $name, string $share): void
    {
        $this->rahim->nominees()->create(['name' => $name, 'share_percent' => $share]);
    }

    // ---- Family members ------------------------------------------------------------------

    public function test_a_family_member_is_added_edited_and_deleted(): void
    {
        $component = Livewire::test(FamilyMembers::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.relation', (string) EmployeeRelation::Spouse->value)
            ->set('form.name', 'Ayesha Begum')
            ->set('form.birth_date', '1992-05-10')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Ayesha Begum')
            ->assertDispatched('toast');

        $ayesha = $this->rahim->familyMembers()->sole();
        $this->assertSame(EmployeeRelation::Spouse, $ayesha->relation);

        $component->call('edit', $ayesha->id)
            ->assertSet('form.name', 'Ayesha Begum')
            ->assertSet('form.birth_date', '1992-05-10')
            ->set('form.occupation', 'Teacher')
            ->call('save');
        $this->assertSame('Teacher', $ayesha->refresh()->occupation);

        $component->call('edit', $ayesha->id)->call('delete');
        $this->assertSame(0, EmployeeFamilyMember::count());
    }

    public function test_relation_and_name_are_required(): void
    {
        Livewire::test(FamilyMembers::class, ['employee' => $this->rahim])
            ->call('add')
            ->call('save')
            ->assertHasErrors(['form.relation' => 'required', 'form.name' => 'required']);
    }

    public function test_the_tab_shows_both_cards(): void
    {
        $this->rahim->familyMembers()->create(['relation' => EmployeeRelation::Father, 'name' => 'Abdul Karim']);
        $this->addNominee('Ayesha Begum', '100');

        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'family']))
            ->assertOk()
            ->assertSeeLivewire(FamilyMembers::class)
            ->assertSeeLivewire(Nominees::class)
            ->assertSee('Abdul Karim')
            ->assertSee('Father')
            ->assertSee('Total 100%');
    }

    // ---- Nominees ------------------------------------------------------------------------

    public function test_shares_may_not_go_above_100(): void
    {
        $this->addNominee('Ayesha', '60');

        Livewire::test(Nominees::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.name', 'Sami')
            ->set('form.share_percent', '40.01')
            ->call('save')
            ->assertHasErrors('form.share_percent')
            ->set('form.share_percent', '40')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('100.00', (string) EmployeeNominee::sum('share_percent'));
    }

    public function test_editing_a_nominee_does_not_count_their_old_share_twice(): void
    {
        $this->addNominee('Ayesha', '100');
        $ayesha = $this->rahim->nominees()->sole();

        Livewire::test(Nominees::class, ['employee' => $this->rahim])
            ->call('edit', $ayesha->id)
            ->set('form.share_percent', '100')
            ->set('form.phone', '01711000000')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_thirds_with_two_decimals_add_up_to_exactly_100(): void
    {
        $this->addNominee('A', '33.33');
        $this->addNominee('B', '33.33');

        Livewire::test(Nominees::class, ['employee' => $this->rahim])
            ->assertSee('Total 66.66%')
            ->assertSee('not 100%')
            ->call('add')->set('form.name', 'C')->set('form.share_percent', '33.34')->call('save')
            ->assertHasNoErrors()
            ->assertSee('Total 100%')
            ->assertDontSee('not 100%');
    }

    public function test_a_minor_needs_a_guardian_kept_as_json(): void
    {
        $component = Livewire::test(Nominees::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.name', 'Sami')
            ->set('form.share_percent', '50')
            ->set('form.is_minor', true)
            ->call('save')
            ->assertHasErrors(['form.guardian_name' => 'required_if_accepted'])
            ->set('form.guardian_name', 'Ayesha Begum')
            ->set('form.guardian_relation', 'Mother')
            ->call('save')
            ->assertHasNoErrors();

        $sami = $this->rahim->nominees()->sole();
        $this->assertTrue($sami->is_minor);
        $this->assertSame('Ayesha Begum', $sami->guardian['name'] ?? null);

        // Opening it again fills the guardian fields; no longer a minor → guardian dropped.
        $component->call('edit', $sami->id)
            ->assertSet('form.guardian_relation', 'Mother')
            ->set('form.is_minor', false)
            ->call('save');
        $this->assertNull($sami->refresh()->guardian);
    }

    public function test_copy_from_family_fills_name_and_relation(): void
    {
        $son = $this->rahim->familyMembers()->create(['relation' => EmployeeRelation::Son, 'name' => 'Sami Uddin']);

        Livewire::test(Nominees::class, ['employee' => $this->rahim])
            ->call('add')
            ->call('copyFamily', $son->id)
            ->assertSet('form.name', 'Sami Uddin')
            ->assertSet('form.relation', EmployeeRelation::Son->value);
    }

    // ---- Security ------------------------------------------------------------------------

    public function test_another_employees_row_cannot_be_opened(): void
    {
        $other = Employee::factory()->create();
        $theirs = $other->nominees()->create(['name' => 'Not yours', 'share_percent' => '100']);
        $theirMember = $other->familyMembers()->create(['relation' => EmployeeRelation::Father, 'name' => 'Not yours']);

        Livewire::test(Nominees::class, ['employee' => $this->rahim])->call('edit', $theirs->id)->assertNotFound();
        Livewire::test(Nominees::class, ['employee' => $this->rahim])->call('copyFamily', $theirMember->id)->assertNotFound();
    }

    public function test_viewers_see_the_lists_but_cannot_change_them(): void
    {
        $this->addNominee('Ayesha', '100');
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);

        Livewire::test(Nominees::class, ['employee' => $this->rahim])
            ->assertSee('Ayesha')
            ->assertDontSee('Add nominee')
            ->call('add')
            ->assertForbidden();
    }

    public function test_deleting_the_employee_deletes_family_and_nominees(): void
    {
        $this->rahim->familyMembers()->create(['relation' => EmployeeRelation::Father, 'name' => 'Abdul Karim']);
        $this->addNominee('Ayesha', '100');

        $this->rahim->delete();

        $this->assertSame(0, EmployeeFamilyMember::count());
        $this->assertSame(0, EmployeeNominee::count());
    }
}
