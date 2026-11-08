<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Models\Activity;
use App\Modules\Hrm\Enums\DocumentType;
use App\Modules\Hrm\Livewire\Employees\BankAccounts;
use App\Modules\Hrm\Livewire\Employees\Documents;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.5 (part 3): Documents (expiry) and Bank (one primary account). [EMP-01, EMP-03]
 */
class DocumentsAndBankTest extends TestCase
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

    // ---- Documents -----------------------------------------------------------------------

    public function test_a_document_with_a_scan_is_added_and_downloads(): void
    {
        Livewire::test(Documents::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.type', (string) DocumentType::Passport->value)
            ->set('form.number', 'A01234567')
            ->set('form.expiry_date', today()->addYears(5)->toDateString())
            ->set('upload', UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Passport')
            ->assertSee('A01234567')
            ->assertDontSee('Expired');

        $doc = $this->rahim->documents()->sole();
        $this->get(route('hrm.employees.attachment', [$this->rahim, 'documents', $doc->id]))->assertDownload('passport.pdf');
    }

    public function test_expired_and_soon_expiring_documents_are_flagged(): void
    {
        $this->rahim->documents()->create(['type' => DocumentType::Visa, 'expiry_date' => today()->subDays(3)]);
        $this->rahim->documents()->create(['type' => DocumentType::WorkPermit, 'expiry_date' => today()->addDays(12)]);
        $this->rahim->documents()->create(['type' => DocumentType::Passport, 'expiry_date' => today()->addYear()]);
        $this->rahim->documents()->create(['type' => DocumentType::NationalId]);

        Livewire::test(Documents::class, ['employee' => $this->rahim])
            ->assertSeeInOrder(['Visa', 'Expired', 'Work permit', 'Expires in 12 days', 'Passport', 'National ID', 'Never']);

        $this->assertEqualsCanonicalizing(
            [DocumentType::Visa, DocumentType::WorkPermit],
            EmployeeDocument::expiringWithin(30)->pluck('type')->all(),
        );
    }

    public function test_other_needs_a_title_and_expiry_cannot_be_before_issue(): void
    {
        Livewire::test(Documents::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.type', (string) DocumentType::Other->value)
            ->set('form.issue_date', '2026-01-10')
            ->set('form.expiry_date', '2026-01-09')
            ->call('save')
            ->assertHasErrors(['form.title' => 'required_if', 'form.expiry_date' => 'after_or_equal'])
            ->set('form.title', 'Gun licence')
            ->set('form.expiry_date', '2031-01-09')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Gun licence');
    }

    // ---- Bank accounts -------------------------------------------------------------------

    private function addAccount(string $no, bool $primary = false): void
    {
        Livewire::test(BankAccounts::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.bank_name', 'Dutch-Bangla Bank')
            ->set('form.account_no', $no)
            ->set('form.is_primary', $primary)
            ->call('save')
            ->assertHasNoErrors();
    }

    /** @return list<string> account numbers that are primary */
    private function primaries(): array
    {
        return $this->rahim->bankAccounts()->where('is_primary', true)->pluck('account_no')->all();
    }

    public function test_the_first_account_becomes_primary_by_itself(): void
    {
        $this->addAccount('111');
        $this->addAccount('222');

        $this->assertSame(['111'], $this->primaries());
    }

    public function test_marking_another_account_primary_moves_the_flag(): void
    {
        $this->addAccount('111');
        $this->addAccount('222', primary: true);

        $this->assertSame(['222'], $this->primaries());
    }

    public function test_unticking_the_only_primary_keeps_it_primary(): void
    {
        $this->addAccount('111');
        $id = $this->rahim->bankAccounts()->sole()->id;

        Livewire::test(BankAccounts::class, ['employee' => $this->rahim])
            ->call('edit', $id)->set('form.is_primary', false)->call('save');

        $this->assertSame(['111'], $this->primaries());
    }

    public function test_deleting_the_primary_promotes_the_next_account(): void
    {
        $this->addAccount('111');
        $this->addAccount('222');
        $first = $this->rahim->bankAccounts()->where('account_no', '111')->value('id');

        Livewire::test(BankAccounts::class, ['employee' => $this->rahim])->call('edit', $first)->call('delete');

        $this->assertSame(['222'], $this->primaries());
    }

    public function test_account_number_is_unique_per_employee_and_routing_has_9_digits(): void
    {
        $this->addAccount('111');
        Employee::factory()->create()->bankAccounts()->create(['bank_name' => 'City Bank', 'account_no' => '999']);

        Livewire::test(BankAccounts::class, ['employee' => $this->rahim])
            ->call('add')
            ->set('form.bank_name', 'City Bank')
            ->set('form.account_no', '111')
            ->set('form.routing_no', '12345')
            ->call('save')
            ->assertHasErrors(['form.account_no' => 'unique', 'form.routing_no' => 'digits'])
            ->set('form.account_no', '999')            // someone else's number is fine
            ->set('form.routing_no', '090261725')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_bank_changes_are_in_the_activity_log(): void
    {
        $this->addAccount('111');
        $this->addAccount('222', primary: true);

        $this->get(route('hrm.employees.show', ['employee' => $this->rahim, 'tab' => 'bank']))->assertOk()->assertSee('Primary');
        $this->assertSame(['222', '111'], $this->rahim->bankAccounts()->pluck('account_no')->all());   // primary first

        $first = $this->rahim->bankAccounts()->where('account_no', '111')->sole();
        $this->assertTrue(
            Activity::query()->where('subject_id', $first->id)
                ->where('subject_type', $first->getMorphClass())->where('event', 'updated')->exists(),
            'Losing the primary flag should be logged.',
        );
    }
}
