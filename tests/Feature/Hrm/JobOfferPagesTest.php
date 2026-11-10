<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\OfferStatus;
use App\Modules\Hrm\Livewire\Recruitment\ApplicationPipeline;
use App\Modules\Hrm\Livewire\Recruitment\OfferDetails;
use App\Modules\Hrm\Livewire\Recruitment\OfferForm;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\JobOffer;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\RejectionReason;
use App\Modules\Hrm\Notifications\OfferLetterSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.7: Make offer (form with live comparison), the offer's page, the letter PDF and email, the answer. [REC-06]
 */
class JobOfferPagesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->tenant = $this->actingAsTenantUser()->tenant;   // Administrator

        $grade = Grade::factory()->create(['name' => 'G5', 'salary_min' => 30000, 'salary_max' => 45000]);
        $request = ManpowerRequest::factory()->approved()->create([
            'designation_id' => Designation::factory()->create(['name' => 'Sales Officer'])->id,
            'grade_id' => $grade->id, 'salary_min' => 35000, 'salary_max' => 50000,
            'needed_by' => today()->addMonths(2)->toDateString(),
        ]);
        $this->application = app(ApplyToPosting::class)->handle(
            Applicant::factory()->create(['first_name' => 'Rahim', 'email' => 'rahim@mail.test', 'expected_salary' => 42000]),
            JobPosting::factory()->for($request)->open()->create(['title' => 'Sales Officer']),
        );
        app(MoveApplication::class)->handle($this->application, ApplicationStage::Interview);
    }

    public function test_make_offer_is_prefilled_compares_live_and_opens_the_offer_page(): void
    {
        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->application->job_posting_id])
            ->assertSeeHtml("create-offer', { applicationId: {$this->application->id} }");

        Livewire::test(OfferForm::class)
            ->dispatch('create-offer', applicationId: $this->application->id)
            ->assertSet('form.gross_salary', '42000.00')                         // the expected salary
            ->assertSet('form.grade_id', $this->application->jobPosting->manpowerRequest->grade_id)
            ->assertSet('form.joining_date', today()->addMonths(2)->toDateString())   // the request's needed-by
            ->assertSee('Within')
            ->set('form.gross_salary', '48000')
            ->assertSee('Above')                                                 // above the G5 band now
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('hrm.recruitment.offers.show', JobOffer::sole()->id));

        $this->assertSame('48000.00', JobOffer::sole()->gross_salary);
        $this->get(route('hrm.recruitment.offers.show', JobOffer::sole()->id))->assertOk()->assertSee('Send for approval');
    }

    public function test_form_rules(): void
    {
        Livewire::test(OfferForm::class)
            ->dispatch('create-offer', applicationId: $this->application->id)
            ->set('form.gross_salary', '')
            ->set('form.joining_date', today()->subDay()->toDateString())
            ->set('form.expires_on', today()->addYear()->toDateString())   // after joining
            ->call('save')
            ->assertHasErrors(['form.gross_salary', 'form.joining_date', 'form.expires_on']);
    }

    public function test_the_letter_is_only_for_an_approved_offer(): void
    {
        $offer = JobOffer::factory()->for($this->application)->create(['designation_id' => $this->application->jobPosting->manpowerRequest->designation_id]);

        $this->get(route('hrm.recruitment.offers.letter', $offer->id))->assertNotFound();   // a draft

        $offer->forceFill(['approved_at' => now()])->save();
        $response = $this->get(route('hrm.recruitment.offers.letter', $offer->id));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertStringContainsString('Offer letter '.$offer->number().' Rahim', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_email_the_letter_then_record_acceptance(): void
    {
        $offer = JobOffer::factory()->for($this->application)->approved()->create(['designation_id' => $this->application->jobPosting->manpowerRequest->designation_id]);

        $page = Livewire::test(OfferDetails::class, ['offerId' => $offer->id])
            ->assertSee('Email letter')
            ->call('emailLetter')
            ->assertDispatched('toast', variant: 'success');

        Notification::assertSentOnDemand(OfferLetterSent::class, function (OfferLetterSent $n, array $channels, AnonymousNotifiable $to) {
            return array_key_exists('rahim@mail.test', $to->routes['mail'])
                && str_starts_with((string) base64_decode($n->pdfBase64), '%PDF')
                && $n->designation === 'Sales Officer';
        });
        $this->assertSame(OfferStatus::LetterSent, $offer->fresh()->status());

        $page->call('openAnswer')
            ->set('answer', JobOffer::ACCEPTED)
            ->call('saveAnswer')
            ->assertHasNoErrors()
            ->assertDontSee('Record answer');
        $this->assertSame(OfferStatus::Accepted, $offer->fresh()->status());
    }

    public function test_declining_needs_a_reason_and_withdraws_the_application(): void
    {
        $offer = JobOffer::factory()->for($this->application)->approved()->create(['designation_id' => $this->application->jobPosting->manpowerRequest->designation_id]);
        $reason = RejectionReason::factory()->create(['name' => 'Took another job']);

        Livewire::test(OfferDetails::class, ['offerId' => $offer->id])
            ->call('openAnswer')
            ->set('answer', JobOffer::DECLINED)
            ->assertSee('Took another job')
            ->call('saveAnswer')
            ->assertHasErrors(['reasonId'])
            ->set('reasonId', $reason->id)
            ->call('saveAnswer')
            ->assertHasNoErrors();

        $this->assertSame(ApplicationStage::Withdrawn, $this->application->fresh()->stage);
    }

    public function test_no_email_address_means_no_email(): void
    {
        $this->application->applicant->update(['email' => null, 'phone' => '01711000000']);
        $offer = JobOffer::factory()->for($this->application)->approved()->create(['designation_id' => $this->application->jobPosting->manpowerRequest->designation_id]);

        Livewire::test(OfferDetails::class, ['offerId' => $offer->id])->call('emailLetter')->assertDispatched('toast', variant: 'danger');

        Notification::assertNothingSent();
        $this->assertNull($offer->fresh()->letter_sent_at);
    }

    public function test_offers_of_other_org_units_give_404(): void
    {
        $offer = JobOffer::factory()->for($this->application)->create(['designation_id' => $this->application->jobPosting->manpowerRequest->designation_id]);

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        Role::where('name', 'like', 'Test role%')->latest('id')->firstOrFail()->update(['org_unit_id' => OrgUnit::factory()->create()->id]);

        $this->get(route('hrm.recruitment.offers.show', $offer->id))->assertNotFound();
        Livewire::test(OfferForm::class)->dispatch('create-offer', applicationId: $this->application->id)->assertNotFound();
    }
}
