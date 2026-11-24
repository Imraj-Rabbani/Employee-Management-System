<?php

namespace Tests\Feature\Core;

use App\Core\Enums\Plan;
use App\Core\Support\PlanLimits;
use App\Core\Support\StorageLimitReached;
use App\Core\Support\TenantUploads;
use App\Livewire\Admin\EditTenantForm;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Hrm\Imports\EmployeeImporter;
use App\Modules\Hrm\Livewire\Employees\CreateEmployee;
use App\Modules\Hrm\Livewire\Employees\InviteUser;
use App\Modules\Hrm\Livewire\Employees\PhotoForm;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Plan limits: Free = 10 employees + 5 GB, Business = 100 employees + 50 GB.
 * (Sign-up with a plan is tested in RegisterCompanyTest.)
 */
class PlanLimitsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser(tenant: $this->createMigratedTenant(['plan' => Plan::Free]))->tenant;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/tenants/'.$this->tenant->code));

        parent::tearDown();
    }

    /** Pretend the company's files already take $bytes. */
    private function storageUsed(int $bytes): void
    {
        $this->partialMock(PlanLimits::class, fn (MockInterface $mock) => $mock->shouldReceive('storageUsed')->andReturn($bytes));
    }

    public function test_the_plans_have_their_limits(): void
    {
        $this->assertSame([10, 5], [Plan::Free->maxEmployees(), Plan::Free->storageGb()]);
        $this->assertSame([100, 50], [Plan::Business->maxEmployees(), Plan::Business->storageGb()]);
        $this->assertSame(Plan::Free, (new Tenant)->plan, 'a new company starts on Free');
    }

    public function test_free_allows_10_employees_and_refuses_the_11th(): void
    {
        Employee::factory()->count(9)->create();

        Livewire::test(CreateEmployee::class)
            ->call('open')
            ->set('form.code', 'E-010')->set('form.first_name', 'Tenth')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(10, Employee::withoutGlobalScopes()->count());

        // Full: the form does not even open, and saving is refused.
        Livewire::test(CreateEmployee::class)
            ->call('open')
            ->assertDispatched('toast', variant: 'danger')
            ->set('form.code', 'E-011')->set('form.first_name', 'Eleventh')
            ->call('save')
            ->assertHasErrors('form.code');
        $this->assertSame(10, Employee::withoutGlobalScopes()->count());
    }

    public function test_business_allows_more_than_10(): void
    {
        tenant()->update(['plan' => Plan::Business]);   // the active company (what PlanLimits reads)
        Employee::factory()->count(10)->create();

        $this->assertNull(app(PlanLimits::class)->employeeProblem());
        $this->assertNotNull(app(PlanLimits::class)->employeeProblem(adding: 91));
    }

    public function test_pending_invitations_take_a_place(): void
    {
        Notification::fake();
        Employee::factory()->count(9)->create();

        Livewire::test(InviteUser::class)->call('open')->set('email', 'one@acme.test')->call('invite')->assertHasNoErrors();
        Livewire::test(InviteUser::class)->call('open')->set('email', 'two@acme.test')->call('invite')->assertHasErrors('email');

        $this->assertFalse(User::where('email', 'two@acme.test')->exists());
    }

    public function test_an_import_that_does_not_fit_is_refused_as_a_whole(): void
    {
        Employee::factory()->count(8)->create();
        $importer = app(EmployeeImporter::class);

        $importer->check([['code' => 'A'], ['code' => 'B'], ['code' => 'C']], auth()->user());

        $this->assertSame(
            'This file has 3 employees, but your Free plan only has room for 2 more (8 of 10 used). Remove 1 row or upgrade your plan.',
            $importer->errors()[0]['message'],
        );
    }

    public function test_an_upload_is_refused_when_storage_is_full(): void
    {
        $this->storageUsed(Plan::Free->storageBytes());
        $employee = Employee::factory()->create();

        Livewire::test(PhotoForm::class, ['employee' => $employee])
            ->call('open')
            ->set('photo', UploadedFile::fake()->image('me.jpg'))
            ->call('save')
            ->assertHasErrors('photo');
        $this->assertNull($employee->refresh()->photo_path);

        $this->expectException(StorageLimitReached::class);
        app(TenantUploads::class)->store(UploadedFile::fake()->image('me.jpg'), 'employees/photos');
    }

    public function test_an_upload_that_fits_is_saved(): void
    {
        $this->storageUsed(Plan::Free->storageBytes() - 1024 * 1024);

        $path = app(TenantUploads::class)->store(UploadedFile::fake()->create('small.pdf', 10), 'tests');

        $this->assertTrue(app(TenantUploads::class)->exists($path));
    }

    public function test_storage_used_adds_up_the_company_files(): void
    {
        $uploads = app(TenantUploads::class);
        // createWithContent: fake()->create() only pretends a size, the file on disk is empty.
        $uploads->store(UploadedFile::fake()->createWithContent('a.pdf', str_repeat('x', 100 * 1024)), 'tests');
        $uploads->store(UploadedFile::fake()->createWithContent('b.pdf', str_repeat('x', 50 * 1024)), 'tests');

        $this->assertSame(150 * 1024, app(PlanLimits::class)->storageUsed());
        $this->assertSame('150 KB', PlanLimits::formatBytes(150 * 1024));
        $this->assertSame('1.5 GB', PlanLimits::formatBytes((int) (1.5 * 1024 ** 3)));
    }

    public function test_company_settings_shows_the_plan_and_usage(): void
    {
        Employee::factory()->count(3)->create();

        $this->get(route('core.settings'))
            ->assertOk()
            ->assertSee('Plan &amp; usage', false)
            ->assertSee('Free plan')
            ->assertSee('3 of 10');
    }

    public function test_the_platform_admin_can_change_the_plan(): void
    {
        $admin = User::factory()->create(['tenant_id' => null]);
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin);

        Livewire::test(EditTenantForm::class)
            ->call('edit', $this->tenant->id)
            ->assertSet('plan', 'free')
            ->set('plan', 'business')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(Plan::Business, $this->tenant->refresh()->plan);
    }
}
