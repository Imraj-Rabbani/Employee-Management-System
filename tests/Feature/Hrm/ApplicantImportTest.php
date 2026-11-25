<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Actions\ImportApplicants;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Livewire\Recruitment\ApplicantImport;
use App\Modules\Hrm\Livewire\Recruitment\ApplicationPipeline;
use App\Modules\Hrm\Livewire\Recruitment\JobPostingForm;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Posting page → Import: applicants from a Google Form's sheet (.csv / .xlsx).
 */
class ApplicantImportTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private JobPosting $posting;

    /** Like a Google Form's responses sheet: the questions are the headers, first column the timestamp. */
    private const HEADERS = ['Timestamp', 'Full name', 'Email address', 'Phone number', 'Gender', 'Date of birth', 'Expected salary', 'Upload your CV'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();   // Administrator
        $this->posting = JobPosting::factory()->open()->create(['title' => 'Sales Officer']);
    }

    /** @param list<list<string>> $lines */
    private function csv(array $lines): UploadedFile
    {
        $text = collect([self::HEADERS, ...$lines])
            ->map(fn ($line) => implode(',', array_map(fn ($cell) => '"'.str_replace('"', '""', $cell).'"', $line)))
            ->join("\n");

        return UploadedFile::fake()->createWithContent('Form responses.csv', $text);
    }

    /** @param list<list<string>> $lines */
    private function uploaded(array $lines): Testable
    {
        return Livewire::test(ApplicantImport::class, ['postingId' => $this->posting->id])
            ->dispatch('import-applicants')
            ->set('file', $this->csv($lines))
            ->assertHasNoErrors();
    }

    public function test_the_columns_of_a_google_form_are_matched_by_their_headers(): void
    {
        $mapping = app(ImportApplicants::class)->guessMapping([...self::HEADERS, 'Current company', 'Present address']);

        $this->assertSame([
            'email' => 2, 'phone' => 3, 'first_name' => 1, 'cv_url' => 7, 'expected_salary' => 6,
            'birth_date' => 5, 'gender' => 4, 'current_employer' => 8, 'address' => 9,
        ], $mapping);
    }

    public function test_a_form_built_from_the_guide_is_matched_completely(): void
    {
        $questions = ImportApplicants::formQuestions();
        $titles = ['Timestamp', ...array_column($questions, 0)];

        $mapping = app(ImportApplicants::class)->guessMapping($titles);

        $expected = [];
        foreach (array_keys($questions) as $i => $field) {
            $expected[$field] = $i + 1;   // column 0 is the timestamp
        }
        $this->assertEqualsCanonicalizing($expected, $mapping);
    }

    public function test_from_the_applicants_list_hr_chooses_the_posting(): void
    {
        $closed = JobPosting::factory()->create(['title' => 'Old Job', 'status' => JobPostingStatus::Closed]);
        $this->get(route('hrm.recruitment.applicants'))->assertOk()->assertSee('Import');

        $component = Livewire::test(ApplicantImport::class)
            ->dispatch('import-applicants')
            ->assertSee('Sales Officer')
            ->assertDontSee('Old Job')
            ->set('file', $this->csv([['', 'Rahim', 'rahim@mail.com', '', '', '', '', '']]))
            ->call('import')
            ->assertHasErrors('postingId');

        $component->set('postingId', $closed->id)->call('import')->assertHasErrors('postingId');   // not offered = not chosen
        $this->assertSame(0, Applicant::count());

        $component->set('postingId', $this->posting->id)
            ->call('import')
            ->assertHasNoErrors()
            ->assertDispatched('applicants-imported');
        $this->assertSame([$this->posting->id], Applicant::sole()->applications->pluck('job_posting_id')->all());
    }

    public function test_the_posting_keeps_the_form_and_responses_links(): void
    {
        $this->posting->update(['form_url' => 'https://forms.gle/abc', 'responses_url' => 'https://docs.google.com/spreadsheets/d/xyz']);

        $this->get(route('hrm.recruitment.postings.show', $this->posting))
            ->assertSee('https://forms.gle/abc')
            ->assertSee('https://docs.google.com/spreadsheets/d/xyz');
        Livewire::test(ApplicantImport::class, ['postingId' => $this->posting->id])
            ->dispatch('import-applicants')
            ->assertSee('Open the responses sheet');

        Livewire::test(JobPostingForm::class)
            ->dispatch('edit-job-posting', id: $this->posting->id)
            ->assertSet('form.form_url', 'https://forms.gle/abc')
            ->set('form.responses_url', 'not a link')
            ->call('save')
            ->assertHasErrors('form.responses_url')
            ->set('form.responses_url', '')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNull($this->posting->fresh()?->responses_url);
    }

    public function test_the_preview_tells_what_happens_to_each_line(): void
    {
        Applicant::factory()->create(['first_name' => 'Karim', 'email' => 'karim@mail.com']);
        $applied = Applicant::factory()->create(['first_name' => 'Salma', 'phone' => '01811000000']);
        $this->posting->applications()->create(['applicant_id' => $applied->id, 'applied_on' => today()]);

        $component = $this->uploaded([
            ['25/11/2026 10:00:00', 'Rahim Uddin', 'Rahim@Mail.com', '1711000000', 'Male', '25/11/1995', '৳ 30,000/-', 'https://drive.google.com/open?id=abc'],
            ['25/11/2026 10:05:00', 'Karim Ali', 'karim@mail.com', '', '', '', '', ''],
            ['25/11/2026 10:06:00', 'Salma B', '', '+880 1811-000000', '', '', '', ''],
            ['25/11/2026 10:07:00', 'Rahim again', 'rahim@mail.com', '', '', '', '', ''],
            ['25/11/2026 10:08:00', 'No Contact', '', '', '', '', '', ''],
            ['25/11/2026 10:09:00', 'Odd Values', 'odd@mail.com', '', 'Prefer not to say', 'soon', 'Negotiable', 'my cv'],
        ])
            ->assertSet('fileName', 'Form responses.csv')
            ->assertSet('mapping.first_name', 1);

        $results = collect($component->viewData('results'))->keyBy('line');
        $this->assertSame('new', $results[2]['status']);
        $this->assertSame('01711000000', $results[2]['values']['phone']);   // the lost 0 is back
        $this->assertSame('existing', $results[3]['status']);
        $this->assertSame('skip', $results[4]['status']);                   // already applied
        $this->assertSame('skip', $results[5]['status']);                   // same person as line 2
        $this->assertSame('Same person as line 2.', $results[5]['message']);
        $this->assertSame('error', $results[6]['status']);
        $this->assertSame('new', $results[7]['status']);
        $this->assertCount(4, $results[7]['warnings']);                    // gender, date, salary, CV link left empty
        $component->assertSee('Import 3 people');
    }

    public function test_import_adds_new_people_applies_known_ones_and_skips_the_rest(): void
    {
        $karim = Applicant::factory()->create(['first_name' => 'Karim', 'email' => 'karim@mail.com', 'national_id' => '123']);

        $this->uploaded([
            ['', 'Rahim Uddin', 'rahim@mail.com', '01711000000', 'Male', '25/11/1995', '30,000', 'https://drive.google.com/open?id=abc'],
            ['', 'Karim Changed', 'karim@mail.com', '', '', '', '', ''],
            ['', 'No Contact', '', '', '', '', '', ''],
        ])
            ->call('import')
            ->assertHasNoErrors()
            ->assertDispatched('applicants-imported')
            ->assertDispatched('toast', variant: 'success', message: '1 new applicant added. 1 already entered person applied. 1 line skipped. Onto '.$this->posting->number().'.');

        $rahim = Applicant::where('email', 'rahim@mail.com')->sole();
        $this->assertSame('Rahim Uddin', $rahim->first_name);   // a full name stays whole
        $this->assertSame(Gender::Male, $rahim->gender);
        $this->assertSame('1995-11-25', $rahim->birth_date?->toDateString());
        $this->assertSame('30000.00', $rahim->expected_salary);
        $this->assertSame('https://drive.google.com/open?id=abc', $rahim->cv_url);
        $this->assertSame('Karim', $karim->fresh()?->first_name);   // details of a known person are not changed

        $this->assertEqualsCanonicalizing([$rahim->id, $karim->id], $this->posting->applications()->pluck('applicant_id')->all());
        $this->assertSame(2, $this->posting->applications()->whereHas('stageChanges')->count());   // history "→ applied"

        // Importing the same file again adds nobody.
        $this->uploaded([['', 'Rahim Uddin', 'rahim@mail.com', '', '', '', '', '']])->assertSee('Already applied to this posting.');
    }

    public function test_an_xlsx_with_numbers_and_date_cells_is_read(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Name', 'Mobile', 'Date of birth'], ['Nadia', 1711222333, 34663]]);   // 34663 = 1994-11-25
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($book))->save($path);

        Livewire::test(ApplicantImport::class, ['postingId' => $this->posting->id])
            ->set('file', UploadedFile::fake()->createWithContent('responses.xlsx', (string) file_get_contents($path)))
            ->assertHasNoErrors()
            ->call('import');
        @unlink($path);

        $nadia = Applicant::sole();
        $this->assertSame('01711222333', $nadia->phone);
        $this->assertSame('1994-11-25', $nadia->birth_date?->toDateString());
    }

    public function test_the_name_column_is_required_and_a_bad_file_is_refused(): void
    {
        $this->uploaded([['', 'Rahim', 'rahim@mail.com', '', '', '', '', '']])
            ->set('mapping.first_name', '')
            ->call('import')
            ->assertHasErrors('mapping.first_name');

        Livewire::test(ApplicantImport::class, ['postingId' => $this->posting->id])
            ->set('file', UploadedFile::fake()->createWithContent('empty.csv', ''))
            ->assertHasErrors('file');

        $this->assertSame(0, Applicant::count());
    }

    public function test_only_a_posting_taking_applications_can_import(): void
    {
        $this->get(route('hrm.recruitment.postings.show', $this->posting))->assertSee('Import');

        $component = $this->uploaded([['', 'Rahim', 'rahim@mail.com', '', '', '', '', '']]);
        $this->posting->update(['status' => JobPostingStatus::Closed]);
        $component->call('import')->assertHasErrors('import');
        $this->assertSame(0, Applicant::count());

        Livewire::test(ApplicationPipeline::class, ['postingId' => $this->posting->id])->assertDontSee('Import');
    }
}
