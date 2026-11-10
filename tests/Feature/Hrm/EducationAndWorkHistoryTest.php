<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Livewire\Employees\Educations;
use App\Modules\Hrm\Livewire\Employees\WorkHistories;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeEducation;
use App\Modules\Hrm\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.5 (part 2): Education and Work History tabs, with attachments. [EMP-03]
 */
class EducationAndWorkHistoryTest extends TestCase
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

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/tenants/'.$this->tenant->code));

        parent::tearDown();
    }

    private function fileUrl(Employee $employee, string $section, int $id): string
    {
        return route('hrm.employees.attachment', [$employee, $section, $id]);
    }

    // ---- Education -----------------------------------------------------------------------

    public function test_education_is_added_with_a_certificate_that_downloads_under_its_own_name(): void
    {
        Livewire::test(Educations::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.degree', 'B Sc (Hon\'s)')
            ->set('form.institution', 'University of Dhaka')
            ->set('form.score', '3.75')
            ->set('form.out_of', '4')
            ->set('form.passing_year', '2015')
            ->set('upload', UploadedFile::fake()->create('BSc certificate.pdf', 200, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('3.75 / 4')
            ->assertSee('Download');

        $row = $this->rahim->educations()->sole();
        $this->assertSame('BSc certificate.pdf', $row->attachment_name);
        $this->assertStringStartsWith('employees/educations/', (string) $row->attachment_path);

        $this->get($this->fileUrl($this->rahim, 'educations', $row->id))
            ->assertOk()
            ->assertDownload('BSc certificate.pdf');
    }

    public function test_education_rules(): void
    {
        Livewire::test(Educations::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.passing_year', '1900')
            ->call('save')
            ->assertHasErrors(['form.degree' => 'required', 'form.passing_year' => 'between'])
            ->set('form.degree', 'HSC')
            ->set('form.passing_year', '2010')
            ->set('form.score', '5.5')
            ->set('form.out_of', '5')
            ->call('save')
            ->assertHasErrors('form.score')
            ->set('upload', UploadedFile::fake()->create('virus.exe', 10))
            ->set('form.score', '5')
            ->call('save')
            ->assertHasErrors(['upload' => 'mimes']);
    }

    public function test_replacing_removing_and_deleting_clean_up_the_files(): void
    {
        $component = Livewire::test(Educations::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.degree', 'SSC')
            ->set('upload', UploadedFile::fake()->image('ssc.jpg'))
            ->call('save');
        $row = $this->rahim->educations()->sole();
        $first = (string) $row->attachment_path;

        // Replace: the old file is deleted.
        $component->call('edit', $row->id)->set('upload', UploadedFile::fake()->image('ssc-new.png'))->call('save');
        $second = (string) $row->refresh()->attachment_path;
        $this->assertFalse(Storage::disk('tenant')->exists($first));
        $this->assertTrue(Storage::disk('tenant')->exists($second));
        $this->assertSame('ssc-new.png', $row->attachment_name);

        // Remove only the file: the row stays.
        $component->call('edit', $row->id)->call('removeAttachment');
        $this->assertNull($row->refresh()->attachment_path);
        $this->assertFalse(Storage::disk('tenant')->exists($second));

        // Delete the row: its file goes too.
        $component->call('edit', $row->id)->set('upload', UploadedFile::fake()->image('again.jpg'))->call('save');
        $third = (string) $row->refresh()->attachment_path;
        $component->call('edit', $row->id)->call('delete');
        $this->assertSame(0, EmployeeEducation::count());
        $this->assertFalse(Storage::disk('tenant')->exists($third));
    }

    public function test_degree_suggestions_come_from_earlier_entries(): void
    {
        Employee::factory()->create()->educations()->create(['degree' => 'M B A']);

        Livewire::test(Educations::class, ['employee' => $this->rahim])->assertViewHas('degreeSuggestions', ['M B A']);
    }

    // ---- Work history --------------------------------------------------------------------

    public function test_a_job_is_added_and_shows_its_duration(): void
    {
        Livewire::test(WorkHistories::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.organization', 'BRAC Bank')
            ->set('form.designation', 'Officer')
            ->set('form.from_date', '2018-01-01')
            ->set('form.to_date', '2020-03-31')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('BRAC Bank')
            ->assertSee('2 years 3 months');
    }

    public function test_to_cannot_be_before_from(): void
    {
        Livewire::test(WorkHistories::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.organization', 'BRAC Bank')
            ->set('form.from_date', '2020-01-01')
            ->set('form.to_date', '2019-12-31')
            ->call('save')
            ->assertHasErrors(['form.to_date' => 'after_or_equal']);
    }

    public function test_the_tabs_render(): void
    {
        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'education']))
            ->assertOk()->assertSeeLivewire(Educations::class);
        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'work-history']))
            ->assertOk()->assertSeeLivewire(WorkHistories::class);
    }

    // ---- Attachment route security -------------------------------------------------------

    public function test_the_file_route_only_reaches_this_employees_rows_and_known_sections(): void
    {
        $other = Employee::factory()->create();
        $theirs = $other->educations()->create(['degree' => 'HSC']);

        $this->get($this->fileUrl($this->rahim, 'educations', $theirs->id))->assertNotFound();   // not Rahim's row
        $this->get($this->fileUrl($this->rahim, 'nominees', 1))->assertNotFound();               // not an attachment section
        $this->get($this->fileUrl($this->rahim, 'educations', 999))->assertNotFound();
    }

    public function test_someone_outside_your_scope_cannot_download(): void
    {
        $finance = OrgUnit::factory()->create(['name' => 'Finance']);
        Livewire::test(Educations::class, ['employee' => $this->rahim])
            ->call('add')->set('form.degree', 'SSC')->set('upload', UploadedFile::fake()->image('ssc.jpg'))->call('save');
        $url = $this->fileUrl($this->rahim, 'educations', $this->rahim->educations()->sole()->id);

        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);
        $user->role()->update(['org_unit_id' => $finance->id]);
        app(Permissions::class)->flush();

        $this->get($url)->assertNotFound();
    }
}
