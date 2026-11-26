<?php

namespace Tests\Feature\Hrm;

use App\Core\Support\TenantUploads;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\DeleteEmployee;
use App\Modules\Hrm\Actions\ScheduleInterview;
use App\Modules\Hrm\Livewire\Employees\EmployeeTable;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\MakesInterviewStages;
use Tests\TestCase;

/**
 * HRM → Employees → Delete: only records without history go (entered by mistake / twice).
 */
class EmployeeDeleteTest extends TestCase
{
    use ActsAsTenantUser;
    use MakesInterviewStages;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator
    }

    public function test_an_employee_without_history_is_deleted_with_their_records_and_files(): void
    {
        $uploads = app(TenantUploads::class);
        $rahim = Employee::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Uddin']);
        $photo = $uploads->store(UploadedFile::fake()->image('rahim.jpg'), 'employees/photos');
        $attachment = $uploads->store(UploadedFile::fake()->create('nid.pdf', 10, 'application/pdf'), 'employees/documents');
        $rahim->update(['photo_path' => $photo]);
        $rahim->documents()->create(['type' => 1, 'attachment_path' => $attachment, 'attachment_name' => 'nid.pdf']);
        $rahim->educations()->create(['degree' => 'BSc', 'institution' => 'DU']);
        $supervised = Employee::factory()->create(['supervisor_id' => $rahim->id]);

        $this->get(route('hrm.employees.index'))->assertSee('Delete');

        Livewire::test(EmployeeTable::class)
            ->set('selected', [(string) $rahim->id])
            ->call('deleteSelected')
            ->assertDispatched('toast', variant: 'success', message: '1 employee deleted.')
            ->assertSet('selected', []);

        $this->assertNull(Employee::find($rahim->id));
        $this->assertSame(0, DB::connection('tenant')->table('employee_documents')->where('employee_id', $rahim->id)->count());
        $this->assertSame(0, DB::connection('tenant')->table('employee_educations')->where('employee_id', $rahim->id)->count());
        $this->assertFalse($uploads->exists($photo));
        $this->assertFalse($uploads->exists($attachment));
        $this->assertNull($supervised->fresh()?->supervisor_id);   // the people they supervised just lose the link
    }

    public function test_employees_with_history_or_a_login_stay_and_the_message_says_why(): void
    {
        $withLogin = Employee::factory()->create(['first_name' => 'Login', 'last_name' => 'Person']);
        $withLogin->forceFill(['user_id' => User::factory()->for($this->tenant)->create()->id])->save();

        $panelist = Employee::factory()->create(['first_name' => 'Panel', 'last_name' => 'Person']);
        $application = app(ApplyToPosting::class)->handle(Applicant::factory()->create(), JobPosting::factory()->open()->create());
        app(ScheduleInterview::class)->handle(
            $application,
            ['interview_stage_id' => $this->stageFor($application)->id, 'scheduled_at' => now()->addDay()->toDateTimeString()], [$panelist->id],
        );

        $free = Employee::factory()->create(['first_name' => 'Free', 'last_name' => 'Person']);

        Livewire::test(EmployeeTable::class)
            ->set('selected', [(string) $withLogin->id, (string) $panelist->id, (string) $free->id])
            ->call('deleteSelected')
            ->assertDispatched('toast', variant: 'warning', message: '1 employee deleted. '
                .'Login Person: Has a login. Remove the login first (Login box on the profile). '
                .'Panel Person: Is on an interview panel. Set the status to Resigned or Terminated instead.');

        $this->assertNotNull($withLogin->fresh());
        $this->assertNotNull($panelist->fresh());
        $this->assertNull($free->fresh());
        $this->assertSame('Is on an interview panel. Set the status to Resigned or Terminated instead.', app(DeleteEmployee::class)->problem($panelist));
    }

    public function test_without_the_permission_there_is_no_delete(): void
    {
        $rahim = Employee::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Uddin']);
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);

        $this->get(route('hrm.employees.index'))->assertOk()->assertDontSee('Delete the ticked employees?');
        Livewire::test(EmployeeTable::class)
            ->set('selected', [(string) $rahim->id])
            ->call('deleteSelected')
            ->assertDispatched('toast', variant: 'error');

        $this->assertNotNull($rahim->fresh());
    }
}
