<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Money;
use App\Core\Support\SpreadsheetDate;
use App\Models\Tenant;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Enums\ImportStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Payroll\Actions\SetEmployeeSalary;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\HeadType;
use App\Modules\Payroll\Enums\ProfileValueType;
use App\Modules\Payroll\Exports\SalaryImportTemplate;
use App\Modules\Payroll\Imports\SalaryImporter;
use App\Modules\Payroll\Livewire\SalaryImports;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\SalaryHead;
use App\Modules\Payroll\Models\SalaryImport;
use App\Modules\Payroll\Models\SalaryProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Salary import from Excel (queued, all or nothing, same rules as the Salary tab).
 * Heads: Basic, House rent (50% of basic), PF (deduction). Profile G5: HR 50% of basic.
 * Employees E-001 (Finance), E-002 (Sales), both joined 2026-01-01.
 */
class SalaryImportTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private SalaryHead $basic;

    private SalaryHead $houseRent;

    private SalaryHead $pf;

    private SalaryProfile $profile;

    private Employee $rahim;

    private Employee $karim;

    private Entity $finance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;

        $this->basic = SalaryHead::factory()->create(['code' => 'BASIC', 'name' => 'Basic', 'is_basic' => true, 'amount' => null, 'sequence' => 1]);
        $this->houseRent = SalaryHead::factory()->percentOfBasic('50')->create(['code' => 'HR', 'name' => 'House rent', 'sequence' => 2]);
        $this->pf = SalaryHead::factory()->create(['code' => 'PF', 'name' => 'Provident fund', 'type' => HeadType::Deduction, 'in_gross' => false, 'sequence' => 9]);
        $this->profile = SalaryProfile::factory()->create(['code' => 'G5', 'name' => 'Grade 5']);
        $this->profile->syncItems([$this->houseRent->id => ['value_type' => 'percent_basic', 'value' => '50']]);

        $this->finance = Entity::factory()->create(['name' => 'Finance']);
        $sales = Entity::factory()->create(['name' => 'Sales']);
        $this->rahim = Employee::factory()->inEntity($this->finance)->create(['code' => 'E-001', 'first_name' => 'Rahim', 'join_date' => '2026-01-01']);
        $this->karim = Employee::factory()->inEntity($sales)->create(['code' => 'E-002', 'first_name' => 'Karim', 'join_date' => '2026-01-01']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/tenants/'.$this->tenant->code));
        File::deleteDirectory(storage_path('framework/testing/imports'));

        parent::tearDown();
    }

    /** @param  list<array<string, mixed>>  $rows  key (column key) => value */
    private function xlsx(array $rows): UploadedFile
    {
        $headings = array_map(fn ($c) => $c[0], SalaryImporter::COLUMNS);
        foreach (SalaryImporter::headColumns() as $key => $head) {
            $headings[$key] = SalaryImporter::headHeading($head);
        }

        $sheet = ($book = new Spreadsheet)->getActiveSheet();
        $c = 1;
        foreach ($headings as $key => $heading) {
            $sheet->setCellValue([$c, 1], $heading);
            foreach ($rows as $r => $row) {
                if (array_key_exists($key, $row)) {
                    $sheet->setCellValue([$c, $r + 2], $row[$key]);
                }
            }
            $c++;
        }

        File::ensureDirectoryExists($dir = storage_path('framework/testing/imports'));
        (new Xlsx($book))->save($path = $dir.'/'.uniqid().'.xlsx');

        return UploadedFile::fake()->createWithContent('salaries.xlsx', (string) file_get_contents($path));
    }

    private function upload(UploadedFile $file): SalaryImport
    {
        Livewire::test(SalaryImports::class)->set('file', $file)->call('start')->assertHasNoErrors();

        return SalaryImport::latest('id')->firstOrFail();
    }

    /** @return array<string, string> head code => amount of the employee's salary on that day */
    private function salaryOn(Employee $employee, string $date): array
    {
        return EmployeeSalary::where('employee_id', $employee->id)->effectiveOn($date)->with('head')->get()
            ->mapWithKeys(fn (EmployeeSalary $r) => [$r->head->code => $r->amount->toDecimal()])->sortKeys()->all();
    }

    /** @return list<string> "row:column" of every error */
    private function errorSpots(SalaryImport $import): array
    {
        return array_map(fn ($e) => $e['row'].':'.$e['column'], $import->errors ?? []);
    }

    public function test_gross_with_a_profile_heads_alone_and_rows_left_out(): void
    {
        $import = $this->upload($this->xlsx([
            ['employee_code' => 'E-001', 'effective_from' => ExcelDate::PHPToExcel(new \DateTime('2026-01-01')), 'salary_profile' => 'g5', 'gross' => 30000, 'head_pf' => 1000],
            ['employee_code' => 'e-002', 'effective_from' => '01/02/2026', 'head_basic' => '25,000', 'head_hr' => 5000],
            ['employee_code' => 'E-001-not-changed', 'employee_name' => 'Nobody'],   // no date: left out, code not even checked
        ]));

        $this->assertSame(ImportStatus::Done, $import->status, json_encode($import->errors));
        $this->assertSame(3, $import->total_rows);
        $this->assertSame(2, $import->imported_rows);

        // Gross 30,000 / 1.5 → basic 20,000, HR 10,000 (worked out, keeps "50% of basic"), PF typed.
        $this->assertSame(['BASIC' => '20000.00', 'HR' => '10000.00', 'PF' => '1000.00'], $this->salaryOn($this->rahim, '2026-01-01'));
        $hr = EmployeeSalary::where('employee_id', $this->rahim->id)->where('salary_head_id', $this->houseRent->id)->sole();
        $this->assertSame(ProfileValueType::PercentOfBasic, $hr->value_type);
        $this->assertSame($this->profile->id, $this->rahim->fresh()->salary_profile_id);

        // Heads alone: as typed, no profile.
        $this->assertSame(['BASIC' => '25000.00', 'HR' => '5000.00'], $this->salaryOn($this->karim, '2026-02-01'));
        $this->assertNull($this->karim->fresh()->salary_profile_id);
    }

    public function test_the_employees_own_profile_is_applied_and_a_basic_works(): void
    {
        $this->karim->update(['salary_profile_id' => $this->profile->id]);

        $import = $this->upload($this->xlsx([['employee_code' => 'E-002', 'effective_from' => '2026-03-01', 'basic' => 10000]]));

        $this->assertSame(ImportStatus::Done, $import->status);
        $this->assertSame(['BASIC' => '10000.00', 'HR' => '5000.00'], $this->salaryOn($this->karim, '2026-03-01'));
    }

    public function test_one_bad_row_means_nothing_is_imported(): void
    {
        EmployeeSalary::query()->delete();
        app(SetEmployeeSalary::class)->handle(
            $this->karim, CarbonImmutable::parse('2026-06-01'),
            SalaryBreakdown::fromLines([new SalaryLine($this->basic, '', Money::of('1000'))]),
        );

        $import = $this->upload($this->xlsx([
            ['employee_code' => 'E-001', 'effective_from' => '2026-01-01', 'head_basic' => 20000],              // fine
            ['employee_code' => 'E-999', 'effective_from' => '2026-01-01', 'head_basic' => 1],                  // unknown
            ['employee_code' => 'E-001', 'effective_from' => '2026-02-01', 'head_basic' => 1],                  // twice
            ['employee_code' => 'E-002', 'effective_from' => '2026-05-01', 'head_basic' => 1],                  // before the latest
        ]));
        $import2 = $this->upload($this->xlsx([
            ['employee_code' => 'E-001', 'effective_from' => '31/02/2026', 'head_basic' => 1],                  // no such date
            ['employee_code' => 'E-002', 'effective_from' => '2026-07-01', 'gross' => 1, 'basic' => 1],         // both
        ]));
        $import3 = $this->upload($this->xlsx([
            ['employee_code' => 'E-001', 'effective_from' => '2026-07-01', 'gross' => 30000],                   // no profile anywhere
            ['employee_code' => 'E-002', 'effective_from' => '2026-07-01', 'salary_profile' => 'X', 'gross' => 1], // unknown profile
        ]));
        $import4 = $this->upload($this->xlsx([
            ['employee_code' => 'E-001', 'effective_from' => '2026-07-01', 'head_hr' => 100],                   // no basic
            ['employee_code' => 'E-002', 'effective_from' => '2026-07-01', 'head_basic' => -5],                 // negative
            ['employee_code' => 'E-002', 'effective_from' => '2026-07-01'],                                     // same code again
        ]));

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertSame(['3:employee_code', '4:employee_code', '5:effective_from'], $this->errorSpots($import));
        $this->assertSame(['2:effective_from', '3:gross'], $this->errorSpots($import2));
        $this->assertSame(['2:salary_profile', '3:salary_profile'], $this->errorSpots($import3));
        $this->assertSame(['2:head_basic', '3:head_basic', '4:employee_code'], $this->errorSpots($import4));
        $this->assertSame([], $this->salaryOn($this->rahim, '2026-12-31'));   // the good row of the first file was not saved
    }

    public function test_employees_outside_the_uploaders_entities_are_refused(): void
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.salary.manage', 'hrm.employee.view']);
        $user->role()->update(['entity_id' => $this->finance->id]);
        app(Permissions::class)->flush();

        $import = $this->upload($this->xlsx([['employee_code' => 'E-002', 'effective_from' => '2026-01-01', 'head_basic' => 1]]));

        $this->assertSame(['2:employee_code'], $this->errorSpots($import));
    }

    public function test_the_template_lists_visible_employees_and_head_columns(): void
    {
        app(SetEmployeeSalary::class)->handle(
            $this->rahim, CarbonImmutable::parse('2026-01-01'),
            SalaryBreakdown::fromLines([new SalaryLine($this->basic, '', Money::of('20000'))]),
            $this->profile,
        );

        $sheets = (new SalaryImportTemplate)->sheets();
        $this->assertSame([...array_column(SalaryImporter::COLUMNS, 0), 'Head BASIC', 'Head HR', 'Head PF'], $sheets[0]->headings());
        $this->assertSame(['E-001', 'Rahim '.$this->rahim->last_name, '20000.00', '', 'G5'], array_map(fn ($v) => is_string($v) ? trim($v) : $v, $sheets[0]->array()[0]));
        $this->assertCount(2, $sheets[0]->array());

        $this->get(route('payroll.setup.import.template'))->assertOk()->assertDownload('salary-import-template.xlsx');
    }

    public function test_the_page_needs_the_manage_permission(): void
    {
        $this->get('/payroll/setup/import')->assertOk()->assertSee('Import salaries from Excel');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['payroll.salary.view']);
        $this->get('/payroll/setup/import')->assertForbidden();
        $this->get(route('payroll.setup.import.template'))->assertForbidden();
    }

    public function test_spreadsheet_dates(): void
    {
        $this->assertSame('2026-01-31', SpreadsheetDate::parse('31/01/2026'));
        $this->assertSame('2026-01-31', SpreadsheetDate::parse(ExcelDate::PHPToExcel(new \DateTime('2026-01-31'))));
        $this->assertNull(SpreadsheetDate::parse(' '));
        $this->expectException(InvalidArgumentException::class);
        SpreadsheetDate::parse('31/02/2026');
    }
}
