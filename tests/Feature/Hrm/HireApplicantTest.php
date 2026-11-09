<?php

namespace Tests\Feature\Hrm;

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\HireApplicant;
use App\Modules\Hrm\Actions\MakeOffer;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\DocumentType;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\JobOffer;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.8: hiring an accepted offer creates the employee and closes the opening. [REC-07]
 */
class HireApplicantTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private HireApplicant $hire;

    private ManpowerRequest $request;

    private Position $seat;

    private JobOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();
        $this->hire = app(HireApplicant::class);

        $sales = OrgUnit::factory()->create(['name' => 'Sales']);
        $this->seat = Position::factory()->create(['org_unit_id' => $sales->id, 'employee_id' => null]);
        $this->request = ManpowerRequest::factory()->approved()->create(['org_unit_id' => $sales->id, 'position_id' => $this->seat->id, 'headcount' => 2]);

        $applicant = Applicant::factory()->create([
            'first_name' => 'Rahim', 'last_name' => 'Uddin', 'email' => 'rahim@mail.test', 'phone' => '01711000000', 'national_id' => '1990123456',
        ]);
        $applicant->educations()->create(['degree' => 'BBA', 'score' => 3.75, 'out_of' => 4, 'passing_year' => 2018]);
        $applicant->workHistories()->create(['organization' => 'ACME Ltd', 'designation' => 'Officer', 'from_date' => '2019-01-01']);
        $applicant->forceFill([
            'cv_path' => app(TenantUploads::class)->store(UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'), 'applicants/cv'),
            'cv_name' => 'Rahim CV.pdf',
        ])->save();

        $application = app(ApplyToPosting::class)->handle($applicant, JobPosting::factory()->for($this->request)->open()->create());
        app(MoveApplication::class)->bySystem($application, ApplicationStage::Offered);

        $this->offer = JobOffer::factory()->for($application->fresh())->approved()->create([
            'grade_id' => Grade::factory()->create()->id,
            'designation_id' => $this->request->designation_id,
            'joining_date' => today()->addWeek()->toDateString(),
        ]);
        app(MakeOffer::class)->accept($this->offer, today());
        $this->offer->refresh();
    }

    /** @return array<string, mixed> */
    private function values(array $overrides = []): array
    {
        return [...$this->hire->defaults($this->offer), 'code' => 'E-1001', ...$overrides];
    }

    public function test_defaults_come_from_the_offer_and_the_request(): void
    {
        $defaults = $this->hire->defaults($this->offer);

        $this->assertSame(today()->addWeek()->toDateString(), $defaults['join_date']);
        $this->assertSame($this->request->org_unit_id, $defaults['org_unit_id']);
        $this->assertSame($this->seat->id, $defaults['position_id']);   // free seat of the request
    }

    public function test_hiring_creates_a_complete_employee(): void
    {
        $employee = $this->hire->handle($this->offer, $this->values(['office_email' => 'rahim@company.test']), 5);

        $this->assertSame('E-1001', $employee->code);
        $this->assertSame('Rahim Uddin', $employee->name);
        $this->assertSame('01711000000', $employee->mobile);
        $this->assertSame('rahim@mail.test', $employee->personal_email);
        $this->assertSame('rahim@company.test', $employee->office_email);
        $this->assertSame('1990123456', $employee->national_id);
        $this->assertSame(EmployeeStatus::Active, $employee->fresh()->status);
        $this->assertSame($this->offer->designation_id, $employee->designation_id);
        $this->assertSame($this->offer->grade_id, $employee->grade_id);
        $this->assertSame(1, $employee->orgAssignments()->count());   // the normal first history row

        // Education, work history and the CV came along; the CV is a COPY.
        $this->assertSame(['BBA'], $employee->educations()->pluck('degree')->all());
        $this->assertSame(['ACME Ltd'], $employee->workHistories()->pluck('organization')->all());
        $cv = $employee->documents()->sole();
        $this->assertSame(DocumentType::Other, $cv->type);
        $this->assertSame('Rahim CV.pdf', $cv->attachment_name);
        $this->assertNotSame($this->offer->application->applicant->cv_path, $cv->attachment_path);
        $this->assertTrue(app(TenantUploads::class)->exists((string) $cv->attachment_path));
    }

    public function test_hiring_closes_the_application_fills_the_seat_and_counts_the_opening(): void
    {
        $employee = $this->hire->handle($this->offer, $this->values(), 5);

        $application = $this->offer->application->fresh();
        $this->assertSame(ApplicationStage::Hired, $application->stage);
        $this->assertSame($employee->id, $application->hired_employee_id);
        $this->assertSame('Hired as E-1001', $application->stageChanges()->get()->last()->note);

        $this->assertSame($employee->id, $this->seat->fresh()->employee_id);
        $this->assertFalse($this->seat->fresh()->is_vacant);

        $this->assertSame(1, $this->request->fresh()->hired_count);
        $this->assertSame(1, $this->request->fresh()->openings());
    }

    public function test_only_an_accepted_offer_and_only_once(): void
    {
        $this->offer->forceFill(['response' => null])->save();
        $this->assertStringContainsString('Only an accepted offer', $this->hire->problem($this->offer->fresh(), $this->values())['message']);

        $this->offer->forceFill(['response' => JobOffer::ACCEPTED])->save();
        $this->hire->handle($this->offer->fresh(), $this->values());

        $this->expectException(LogicException::class);
        $this->hire->handle($this->offer->fresh(), $this->values(['code' => 'E-1002']));
    }

    public function test_code_office_email_position_and_openings_are_checked(): void
    {
        $other = Employee::factory()->create(['code' => 'E-1001', 'office_email' => 'taken@company.test']);

        $this->assertSame('code', $this->hire->problem($this->offer, $this->values())['field']);
        $this->assertSame('office_email', $this->hire->problem($this->offer, $this->values(['code' => 'E-2', 'office_email' => 'taken@company.test']))['field']);

        $this->seat->update(['employee_id' => $other->id]);
        $this->assertSame('position_id', $this->hire->problem($this->offer, $this->values(['code' => 'E-2', 'position_id' => $this->seat->id]))['field']);

        $this->request->forceFill(['hired_count' => 2])->save();   // both seats filled meanwhile
        $this->assertStringContainsString('already filled', $this->hire->problem($this->offer->fresh(), $this->values(['code' => 'E-2', 'position_id' => null]))['message']);
    }

    public function test_a_refused_hire_changes_nothing(): void
    {
        $before = Employee::withoutGlobalScopes()->count();
        $this->request->forceFill(['hired_count' => 2])->save();   // no opening: refused

        try {
            $this->hire->handle($this->offer->fresh(), $this->values());
            $this->fail('Hired without an opening.');
        } catch (LogicException) {
        }

        $this->assertSame($before, Employee::withoutGlobalScopes()->count());
        $this->assertSame(ApplicationStage::Offered, $this->offer->application->fresh()->stage);
    }
}
