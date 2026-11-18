<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Livewire\Employees\CreateEmployee;
use App\Modules\Hrm\Livewire\Employees\JobForm;
use App\Modules\Hrm\Livewire\Employees\PersonalForm;
use App\Modules\Hrm\Livewire\Employees\PhotoForm;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use App\Modules\Hrm\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * New employee, edit Personal / Job, photo.
 *
 * Departments Finance and Sales. Rahim works in Finance, Salma in Sales.
 */
class EmployeeFormsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Entity $finance;

    private Entity $sales;

    private Employee $rahim;

    private Employee $salma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;

        $department = EntityType::firstWhere('name', 'Department');
        $this->finance = Entity::factory()->ofType($department)->create(['name' => 'Finance']);
        $this->sales = Entity::factory()->ofType($department)->create(['name' => 'Sales']);

        $this->rahim = Employee::factory()->inEntity($this->finance)->create(['code' => 'E-001', 'first_name' => 'Rahim', 'last_name' => 'Uddin']);
        $this->salma = Employee::factory()->inEntity($this->sales)->create(['code' => 'E-002', 'first_name' => 'Salma', 'last_name' => 'Begum']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/tenants/'.$this->tenant->code));

        parent::tearDown();
    }

    /** A user whose role only reaches Department Finance. */
    private function financeUser(array $permissions): User
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: $permissions);
        $user->role()->update(['entity_id' => $this->finance->id]);
        app(Permissions::class)->flush();

        return $user;
    }

    // ---- New employee -------------------------------------------------------------

    public function test_a_new_employee_is_created_and_the_profile_opens(): void
    {
        Livewire::test(CreateEmployee::class)
            ->call('open')
            ->set('form.code', 'E-100')
            ->set('form.first_name', 'Nadia')
            ->set('form.gender', (string) Gender::Female->value)
            ->set("form.entities.{$this->finance->entity_type_id}", (string) $this->finance->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('hrm.employees.show', Employee::where('code', 'E-100')->firstOrFail()));

        $nadia = Employee::where('code', 'E-100')->firstOrFail();
        $this->assertSame(Gender::Female, $nadia->gender);
        $this->assertSame([$this->finance->id], $nadia->entityIds());
        $this->assertSame($nadia->join_date->toDateString(), $nadia->entityAssignments()->sole()->effective_from->toDateString());
        $this->assertTrue($nadia->join_date->isToday());
    }

    public function test_code_and_name_are_required_and_the_code_unique_but_entities_are_optional(): void
    {
        $departmentField = "form.entities.{$this->finance->entity_type_id}";

        Livewire::test(CreateEmployee::class)
            ->call('open')
            ->assertViewHas('choices', fn (array $choices) => array_column($choices, 'name') === ['Department', 'Division', 'Branch', 'Location', 'Area'])
            ->set('form.code', 'E-001')
            ->call('save')
            ->assertHasErrors(['form.code' => 'unique', 'form.first_name' => 'required'])
            ->assertHasNoErrors($departmentField);
    }

    public function test_a_finance_user_must_create_someone_in_finance(): void
    {
        $this->financeUser(['hrm.employee.view', 'hrm.employee.create']);
        $departmentField = "form.entities.{$this->finance->entity_type_id}";

        $form = Livewire::test(CreateEmployee::class)
            ->call('open')
            ->assertSet($departmentField, (string) $this->finance->id)   // preselected: their only choice
            ->assertViewHas('choices', fn (array $choices) => $choices[$this->finance->entity_type_id]['required']
                && array_keys($choices[$this->finance->entity_type_id]['options']) === [$this->finance->id])
            ->set('form.code', 'E-100')
            ->set('form.first_name', 'Nadia')
            ->set($departmentField, (string) $this->sales->id)
            ->call('save')
            ->assertHasErrors([$departmentField => 'in']);

        $form->set($departmentField, '')->call('save')->assertHasErrors([$departmentField => 'required']);
        $form->set($departmentField, (string) $this->finance->id)->call('save')->assertHasNoErrors();
    }

    public function test_the_new_button_needs_the_create_permission(): void
    {
        $this->get(route('hrm.employees.index'))->assertSee('Add new');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);
        $this->get(route('hrm.employees.index'))->assertDontSee('Add new');
        Livewire::test(CreateEmployee::class)->call('open')->assertForbidden();
    }

    // ---- Personal -----------------------------------------------------------------

    public function test_personal_details_are_saved(): void
    {
        Livewire::test(PersonalForm::class, ['employee' => $this->rahim])
            ->call('open')
            ->assertSet('form.first_name', 'Rahim')
            ->set('form.name_local', 'রহিম উদ্দিন')
            ->set('form.national_id', '1990123456789')
            ->set('form.mobile', '01711000000')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'personal']));

        $this->rahim->refresh();
        $this->assertSame('রহিম উদ্দিন', $this->rahim->name_local);
        $this->assertSame('1990123456789', $this->rahim->national_id);
        $this->assertNull($this->rahim->tin);   // empty input → NULL, so the unique index allows many
    }

    public function test_national_id_and_office_email_must_be_unique(): void
    {
        $this->salma->update(['national_id' => '111', 'office_email' => 'salma@example.com']);

        Livewire::test(PersonalForm::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.national_id', '111')
            ->set('form.office_email', 'salma@example.com')
            ->call('save')
            ->assertHasErrors(['form.national_id' => 'unique', 'form.office_email' => 'unique']);
    }

    public function test_editing_needs_the_update_permission_and_scope(): void
    {
        $this->get(route('hrm.employees.show', $this->rahim))->assertSee('Edit personal details');

        $this->financeUser(['hrm.employee.view']);
        $this->get(route('hrm.employees.show', $this->rahim))->assertDontSee('Edit personal details');
        Livewire::test(PersonalForm::class, ['employee' => $this->rahim])->call('open')->assertForbidden();

        // With the permission: Rahim (Finance) can be edited, Salma (Sales) is not even found.
        $this->financeUser(['hrm.employee.view', 'hrm.employee.update']);
        Livewire::test(PersonalForm::class, ['employee' => $this->rahim])->call('open')->assertOk();
        Livewire::test(PersonalForm::class, ['employee' => $this->salma])->call('open')->assertNotFound();
    }

    // ---- Job ----------------------------------------------------------------------

    public function test_job_details_are_saved(): void
    {
        Livewire::test(JobForm::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.code', 'E-001A')
            ->set('form.supervisor_id', (string) $this->salma->id)
            ->set('form.confirmation_date', now()->addYear()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->rahim->refresh();
        $this->assertSame('E-001A', $this->rahim->code);
        $this->assertSame($this->salma->id, $this->rahim->supervisor_id);
    }

    public function test_an_employee_cannot_report_to_themselves_or_make_a_loop(): void
    {
        Livewire::test(JobForm::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.supervisor_id', (string) $this->rahim->id)
            ->call('save')
            ->assertHasErrors('form.supervisor_id');

        $this->salma->update(['supervisor_id' => $this->rahim->id]);   // Salma → Rahim

        Livewire::test(JobForm::class, ['employee' => $this->rahim])     // Rahim → Salma would loop
            ->call('open')
            ->set('form.supervisor_id', (string) $this->salma->id)
            ->call('save')
            ->assertHasErrors('form.supervisor_id');
    }

    public function test_confirmation_cannot_be_before_joining(): void
    {
        Livewire::test(JobForm::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.join_date', '2026-01-01')
            ->set('form.confirmation_date', '2025-12-31')
            ->call('save')
            ->assertHasErrors(['form.confirmation_date' => 'after_or_equal']);
    }

    public function test_a_position_is_filled_and_the_old_one_freed(): void
    {
        $officer = Position::factory()->create(['entity_id' => $this->finance->id, 'name' => 'Officer']);
        $manager = Position::factory()->create(['entity_id' => $this->finance->id, 'name' => 'Manager']);

        Livewire::test(JobForm::class, ['employee' => $this->rahim])
            ->call('open')->set('form.position_id', (string) $officer->id)->call('save')->assertHasNoErrors();
        $this->assertSame($this->rahim->id, $officer->refresh()->employee_id);
        $this->assertFalse($officer->is_vacant);

        Livewire::test(JobForm::class, ['employee' => $this->rahim])
            ->call('open')->set('form.position_id', (string) $manager->id)->call('save')->assertHasNoErrors();
        $this->assertNull($officer->refresh()->employee_id);
        $this->assertTrue($officer->is_vacant);
        $this->assertSame($this->rahim->id, $manager->refresh()->employee_id);
    }

    public function test_a_position_held_by_someone_else_is_refused(): void
    {
        $officer = Position::factory()->create(['entity_id' => $this->finance->id, 'employee_id' => $this->salma->id, 'is_vacant' => false]);

        Livewire::test(JobForm::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('form.position_id', (string) $officer->id)
            ->call('save')
            ->assertHasErrors('form.position_id');
    }

    // ---- Photo --------------------------------------------------------------------

    public function test_a_photo_is_uploaded_shown_replaced_and_removed(): void
    {
        Livewire::test(PhotoForm::class, ['employee' => $this->rahim])
            ->call('open')
            ->set('photo', UploadedFile::fake()->image('rahim.jpg', 300, 300))
            ->call('save')
            ->assertHasNoErrors();

        $first = $this->rahim->refresh()->photo_path;
        $this->assertNotNull($first);
        $this->assertTrue(Storage::disk('tenant')->exists($first));
        $this->get($this->rahim->photo_url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('hrm.employees.show', $this->rahim))->assertSee($this->rahim->photo_url, false);

        // Replacing deletes the old file.
        Livewire::test(PhotoForm::class, ['employee' => $this->rahim])
            ->set('photo', UploadedFile::fake()->image('new.png'))
            ->call('save');
        $this->assertFalse(Storage::disk('tenant')->exists($first));

        $second = $this->rahim->refresh()->photo_path;
        Livewire::test(PhotoForm::class, ['employee' => $this->rahim])->call('remove');
        $this->assertNull($this->rahim->refresh()->photo_path);
        $this->assertFalse(Storage::disk('tenant')->exists((string) $second));
        $this->get(route('hrm.employees.photo', $this->rahim))->assertNotFound();
    }

    public function test_only_images_up_to_2_mb_are_accepted(): void
    {
        Livewire::test(PhotoForm::class, ['employee' => $this->rahim])
            ->set('photo', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('photo');

        Livewire::test(PhotoForm::class, ['employee' => $this->rahim])
            ->set('photo', UploadedFile::fake()->image('big.jpg')->size(3000))
            ->call('save')
            ->assertHasErrors(['photo' => 'max']);
    }

    public function test_someone_outside_your_scope_cannot_load_the_photo(): void
    {
        Livewire::test(PhotoForm::class, ['employee' => $this->salma])
            ->set('photo', UploadedFile::fake()->image('salma.jpg'))
            ->call('save');
        $url = $this->salma->refresh()->photo_url;

        $this->financeUser(['hrm.employee.view']);
        $this->get((string) $url)->assertNotFound();
    }
}
