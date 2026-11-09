<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Hrm\Livewire\Recruitment\InterviewFactorForm;
use App\Modules\Hrm\Livewire\Recruitment\RejectionReasonForm;
use App\Modules\Hrm\Models\InterviewFactor;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.1: HRM → Recruitment → Setup (interview factors, rejection reasons). [REC-04, REC-05]
 */
class RecruitmentSetupPagesTest extends TestCase
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
        InterviewFactor::factory()->create(['name' => 'Communication']);
        RejectionReason::factory()->create(['name' => 'Did not attend']);

        // Since 6.2 the sidebar opens Manpower requests; Setup is a button on that page.
        $this->get('/hrm/recruitment/requests')->assertSee(route('hrm.recruitment.setup.factors'));
        $this->get('/hrm/recruitment/setup/factors')->assertOk()->assertSee('Communication');
        $this->get('/hrm/recruitment/setup/reasons')->assertOk()->assertSee('Did not attend');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.setup']);
        $this->get('/hrm/recruitment/setup/factors')->assertOk();

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get('/hrm/recruitment/setup/factors')->assertForbidden();
    }

    public function test_a_factor_is_created_and_edited_through_the_modal(): void
    {
        Livewire::test(InterviewFactorForm::class)
            ->dispatch('create-interview-factor')
            ->set('form.name', 'Communication')
            ->set('form.weight', '40')
            ->set('form.is_required', true)
            ->set('form.description', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('interview-factor-saved');

        $factor = InterviewFactor::sole();
        $this->assertSame('40.00', $factor->weight);
        $this->assertTrue($factor->is_required);
        $this->assertNull($factor->description);

        Livewire::test(InterviewFactorForm::class)
            ->dispatch('edit-interview-factor', id: $factor->id)
            ->assertSet('form.name', 'Communication')
            ->set('form.weight', '100')   // editing: its own old 40% does not count against it
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('100.00', $factor->fresh()->weight);
    }

    public function test_active_weights_may_not_go_over_100_percent(): void
    {
        InterviewFactor::factory()->create(['weight' => 70]);
        InterviewFactor::factory()->create(['weight' => 50, 'is_active' => false]);   // inactive: not counted

        Livewire::test(InterviewFactorForm::class)
            ->dispatch('create-interview-factor')
            ->assertSee('Other active factors: 70%')
            ->set('form.name', 'Attitude')
            ->set('form.weight', '30.5')
            ->call('save')
            ->assertHasErrors(['form.weight'])
            ->set('form.weight', '30')
            ->call('save')
            ->assertHasNoErrors();

        // An inactive factor may have any weight: it is not on the score sheet.
        Livewire::test(InterviewFactorForm::class)
            ->dispatch('create-interview-factor')
            ->set('form.name', 'Leadership')
            ->set('form.weight', '20')
            ->set('form.is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(4, InterviewFactor::count());
    }

    public function test_a_reason_is_created_and_names_are_unique(): void
    {
        RejectionReason::factory()->create(['name' => 'Did not attend']);

        Livewire::test(RejectionReasonForm::class)
            ->dispatch('create-rejection-reason')
            ->set('form.name', 'Did not attend')
            ->call('save')
            ->assertHasErrors(['form.name'])
            ->set('form.name', 'Salary expectation too high')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('rejection-reason-saved');

        $this->assertSame(2, RejectionReason::count());
    }
}
