<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.4: applicants, duplicate check, and ApplyToPosting. [REC-03]
 */
class ApplicantTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();
    }

    public function test_phone_and_email_are_stored_normalised(): void
    {
        $this->assertSame('01711000000', Applicant::normalizePhone('+880 1711-000000'));
        $this->assertSame('01711000000', Applicant::normalizePhone('8801711000000'));
        $this->assertSame('01711000000', Applicant::normalizePhone('01711 000 000'));
        $this->assertNull(Applicant::normalizePhone('  '));

        $applicant = Applicant::create(['first_name' => 'Rahim', 'email' => '  Rahim@Mail.COM ', 'phone' => '+880 1711-000000'])->fresh();
        $this->assertSame('rahim@mail.com', $applicant->email);
        $this->assertSame('01711000000', $applicant->phone);
    }

    public function test_duplicates_are_found_however_the_number_or_email_is_written(): void
    {
        $rahim = Applicant::factory()->create(['email' => 'rahim@mail.com', 'phone' => '01711000000']);

        $this->assertSame([$rahim->id], Applicant::duplicatesOf('RAHIM@mail.com', null)->pluck('id')->all());
        $this->assertSame([$rahim->id], Applicant::duplicatesOf(null, '+880 1711 000000')->pluck('id')->all());
        $this->assertSame([$rahim->id], Applicant::duplicatesOf('other@mail.com', '01711-000000')->pluck('id')->all());
        $this->assertTrue(Applicant::duplicatesOf('other@mail.com', '01811000000')->isEmpty());
        $this->assertTrue(Applicant::duplicatesOf(null, null)->isEmpty());
        $this->assertTrue(Applicant::duplicatesOf('rahim@mail.com', null, exceptId: $rahim->id)->isEmpty());   // editing Rahim
    }

    public function test_the_database_refuses_a_duplicate_too(): void
    {
        Applicant::factory()->create(['phone' => '01711000000']);

        $this->expectException(QueryException::class);
        Applicant::factory()->create(['phone' => '+8801711000000']);
    }

    public function test_applying_to_an_open_posting(): void
    {
        $posting = JobPosting::factory()->open()->create();
        $applicant = Applicant::factory()->create();

        $application = app(ApplyToPosting::class)->handle($applicant, $posting, 7);

        $this->assertSame(ApplicationStage::Applied, $application->fresh()->stage);
        $this->assertSame(today()->toDateString(), $application->applied_on->toDateString());
        $this->assertSame(7, $application->created_by_user_id);
        $this->assertSame([$application->id], $posting->applications->pluck('id')->all());
    }

    public function test_only_once_per_posting_but_to_several_postings(): void
    {
        $applicant = Applicant::factory()->create();
        $first = JobPosting::factory()->open()->create();
        $second = JobPosting::factory()->open()->create();
        $apply = app(ApplyToPosting::class);

        $apply->handle($applicant, $first);
        $apply->handle($applicant, $second);
        $this->assertStringContainsString('already applied', (string) $apply->problem($applicant, $first));

        $this->expectException(LogicException::class);
        $apply->handle($applicant, $first);
    }

    public function test_a_posting_must_be_taking_applications(): void
    {
        $applicant = Applicant::factory()->create();
        $apply = app(ApplyToPosting::class);

        foreach ([
            JobPosting::factory()->create(),                                                     // draft
            JobPosting::factory()->create(['status' => JobPostingStatus::Closed]),
            JobPosting::factory()->open()->create(['open_date' => today()->addDay()]),         // not yet
            JobPosting::factory()->open()->create(['open_date' => today()->subMonth(), 'close_date' => today()->subDay()]),
        ] as $posting) {
            $this->assertStringContainsString('not taking applications', (string) $apply->problem($applicant, $posting));
        }
    }

    public function test_an_applicant_with_applications_cannot_be_deleted(): void
    {
        $application = Application::factory()->create();

        $this->expectException(QueryException::class);
        $application->applicant->delete();
    }
}
