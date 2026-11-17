<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Modules\Core\Services\Permissions;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\ImportStatus;
use App\Modules\Hrm\Exports\EmployeeImportTemplate;
use App\Modules\Hrm\Imports\EmployeeImporter;
use App\Modules\Hrm\Livewire\Employees\EmployeeImports;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeImport;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Excel import of employees with a validation report, run as a queued job.
 *
 * Tests run the queue "sync" (phpunit.xml), so the job runs inside start().
 * Departments Finance (code FIN) and Sales. Existing employee: E-001 Rahim (Finance).
 */
class EmployeeImportTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Entity $finance;

    private Entity $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenantUser()->tenant;

        $department = EntityType::firstWhere('name', 'Department');
        $this->finance = Entity::factory()->ofType($department)->create(['name' => 'Finance', 'code' => 'FIN']);
        $this->sales = Entity::factory()->ofType($department)->create(['name' => 'Sales']);
        Designation::factory()->create(['name' => 'Senior Officer']);

        Employee::factory()->inEntity($this->finance)->create(['code' => 'E-001', 'first_name' => 'Rahim']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/tenants/'.$this->tenant->code));
        File::deleteDirectory(storage_path('framework/testing/imports'));

        parent::tearDown();
    }

    /**
     * An .xlsx with the template headings and these rows (key => value, missing keys stay empty).
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>|null  $columns  default: all template columns
     */
    private function xlsx(array $rows, ?array $columns = null): UploadedFile
    {
        $all = EmployeeImporter::columns(app(EmployeeVisibility::class)->entityChoicesFor(null));   // + "Entity: Department" ...
        $columns ??= array_keys($all);
        $sheet = ($book = new Spreadsheet)->getActiveSheet();

        foreach ($columns as $c => $key) {
            $sheet->setCellValue([$c + 1, 1], $all[$key][0] ?? $key);
        }
        foreach ($rows as $r => $row) {
            foreach ($columns as $c => $key) {
                if (array_key_exists($key, $row)) {
                    $sheet->setCellValue([$c + 1, $r + 2], $row[$key]);
                }
            }
        }

        File::ensureDirectoryExists($dir = storage_path('framework/testing/imports'));
        (new Xlsx($book))->save($path = $dir.'/'.uniqid().'.xlsx');

        return UploadedFile::fake()->createWithContent('employees.xlsx', (string) file_get_contents($path));
    }

    private function upload(UploadedFile $file): EmployeeImport
    {
        Livewire::test(EmployeeImports::class)->set('file', $file)->call('start')->assertHasNoErrors();

        return EmployeeImport::latest('id')->firstOrFail();
    }

    /** @return list<string> "row:column" of every error */
    private function errorSpots(EmployeeImport $import): array
    {
        return array_map(fn ($e) => $e['row'].':'.$e['column'], $import->errors ?? []);
    }

    // ---- Happy path ----------------------------------------------------------------------

    public function test_a_valid_file_imports_every_row(): void
    {
        $import = $this->upload($this->xlsx([
            [
                'employee_code' => 'E-100', 'first_name' => 'Nadia', 'last_name' => 'Islam', 'gender' => 'female',
                'join_date' => ExcelDate::PHPToExcel(new \DateTime('2024-02-01')),   // a real Excel date cell
                'birth_date' => '14/05/1990',                                          // text, day first
                'entity_department' => 'fin',                                        // code, any case
                'designation' => 'senior officer',                                     // name, any case
                'supervisor_code' => 'E-101',                                          // further down in the file
            ],
            ['employee_code' => 'E-101', 'first_name' => 'Kamal', 'join_date' => '2020-01-01', 'entity_department' => 'Sales', 'supervisor_code' => 'E-001'],
        ]));

        $this->assertSame(ImportStatus::Done, $import->status);
        $this->assertSame(2, $import->imported_rows);
        $this->assertNull($import->errors);

        $nadia = Employee::where('code', 'E-100')->firstOrFail();
        $kamal = Employee::where('code', 'E-101')->firstOrFail();
        $this->assertSame(Gender::Female, $nadia->gender);
        $this->assertSame('2024-02-01', $nadia->join_date->toDateString());
        $this->assertSame('1990-05-14', $nadia->birth_date?->toDateString());
        $this->assertSame([$this->finance->id], $nadia->entityIds());
        $this->assertSame('Senior Officer', $nadia->designation?->name);
        $this->assertSame($kamal->id, $nadia->supervisor_id);
        $this->assertSame(Employee::where('code', 'E-001')->value('id'), $kamal->supervisor_id);
        $this->assertSame(1, $nadia->entityAssignments()->count(), 'Entity history starts on import.');
    }

    public function test_empty_rows_are_skipped(): void
    {
        $import = $this->upload($this->xlsx([
            ['employee_code' => 'E-100', 'first_name' => 'Nadia', 'join_date' => '2024-01-01', 'entity_department' => 'FIN'],
            [],
            ['employee_code' => 'E-101', 'first_name' => 'Kamal', 'join_date' => '2024-01-01', 'entity_department' => 'FIN'],
        ]));

        $this->assertSame(ImportStatus::Done, $import->status);
        $this->assertSame(2, $import->total_rows);
    }

    // ---- Validation report ---------------------------------------------------------------

    public function test_any_error_imports_nothing_and_lists_every_problem_by_row(): void
    {
        Entity::factory()->create(['name' => 'Accounts']);
        Entity::factory()->create(['name' => 'Accounts']);   // same name twice → ambiguous

        $import = $this->upload($this->xlsx([
            ['employee_code' => 'E-200', 'first_name' => 'Fine', 'join_date' => '2024-01-01', 'entity_department' => 'FIN'],   // row 2 ok
            ['employee_code' => 'E-001', 'first_name' => 'Dup', 'join_date' => '2024-01-01', 'entity_department' => 'FIN'],    // row 3 code exists
            ['employee_code' => 'E-200', 'first_name' => 'Twin', 'join_date' => '2024-01-01', 'entity_department' => 'FIN'],   // row 4 code twice in file
            ['employee_code' => 'E-202', 'first_name' => '', 'join_date' => '31/02/2024', 'entity_department' => 'Nowhere'],   // row 5
            ['employee_code' => 'E-203', 'first_name' => 'X', 'join_date' => '2024-01-01', 'entity_department' => 'Accounts', 'gender' => 'm?'], // row 6
            ['employee_code' => 'E-204', 'first_name' => 'Y', 'join_date' => '2024-01-01', 'entity_department' => 'FIN', 'supervisor_code' => 'E-999'], // row 7
        ]));

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertSame(0, $import->imported_rows);
        $this->assertSame(6, $import->total_rows);
        $this->assertSame(1, Employee::count(), 'Nothing imported, not even the good row.');

        $this->assertEqualsCanonicalizing([
            '3:employee_code', '4:employee_code',
            '5:first_name', '5:join_date', '5:entity_department',
            '6:entity_department', '6:gender',
            '7:supervisor_code',
        ], $this->errorSpots($import));
    }

    public function test_supervisor_loops_in_the_file_are_refused(): void
    {
        $import = $this->upload($this->xlsx([
            ['employee_code' => 'A', 'first_name' => 'A', 'join_date' => '2024-01-01', 'entity_department' => 'FIN', 'supervisor_code' => 'B'],
            ['employee_code' => 'B', 'first_name' => 'B', 'join_date' => '2024-01-01', 'entity_department' => 'FIN', 'supervisor_code' => 'A'],
        ]));

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertContains('2:supervisor_code', $this->errorSpots($import));
    }

    public function test_unique_ids_are_checked_against_the_database_and_the_file(): void
    {
        Employee::where('code', 'E-001')->update(['national_id' => '111']);

        $import = $this->upload($this->xlsx([
            ['employee_code' => 'E-100', 'first_name' => 'A', 'join_date' => '2024-01-01', 'entity_department' => 'FIN', 'national_id' => '111'],
            ['employee_code' => 'E-101', 'first_name' => 'B', 'join_date' => '2024-01-01', 'entity_department' => 'FIN', 'office_email' => 'x@example.com'],
            ['employee_code' => 'E-102', 'first_name' => 'C', 'join_date' => '2024-01-01', 'entity_department' => 'FIN', 'office_email' => 'X@example.com'],
        ]));

        $this->assertEqualsCanonicalizing(['2:national_id', '4:office_email'], $this->errorSpots($import));
    }

    public function test_a_file_without_the_required_columns_is_refused(): void
    {
        $import = $this->upload($this->xlsx([['first_name' => 'A']], ['first_name', 'last_name']));

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertStringContainsString('Missing columns', $import->errors[0]['message'] ?? '');
    }

    public function test_a_finance_user_cannot_import_into_sales(): void
    {
        $user = $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view', 'hrm.employee.create']);
        $user->role()->update(['entity_id' => $this->finance->id]);
        app(Permissions::class)->flush();

        $import = $this->upload($this->xlsx([
            ['employee_code' => 'E-100', 'first_name' => 'A', 'join_date' => '2024-01-01', 'entity_department' => 'Sales'],
        ]));

        $this->assertSame(['2:entity_department'], $this->errorSpots($import));

        // The Department column is required for them (otherwise the new employee would be invisible to them).
        $import = $this->upload($this->xlsx([['employee_code' => 'E-100', 'first_name' => 'A', 'join_date' => '2024-01-01']]));
        $this->assertSame(['2:entity_department'], $this->errorSpots($import));
    }

    // ---- Page, template, permissions -----------------------------------------------------

    public function test_the_page_shows_imports_and_their_problems(): void
    {
        $this->upload($this->xlsx([['employee_code' => 'E-001', 'first_name' => 'Dup', 'join_date' => '2024-01-01', 'entity_department' => 'FIN']]));

        $this->get(route('hrm.employees.import'))
            ->assertOk()
            ->assertSee('Not imported')
            ->assertSee('Employee code E-001 already exists.')
            ->assertSee('Employee code');   // column shown by its heading, not its key
    }

    public function test_the_template_downloads_and_the_importer_accepts_its_own_example_row(): void
    {
        $this->get(route('hrm.employees.import.template'))->assertOk()->assertDownload('employee-import-template.xlsx');

        // Round trip: the template's example row (first entity of each type, by code) imports cleanly.
        $raw = \Maatwebsite\Excel\Facades\Excel::raw(
            new EmployeeImportTemplate(app(EmployeeVisibility::class)->entityChoicesFor(null)),
            Excel::XLSX,
        );
        File::ensureDirectoryExists($dir = storage_path('framework/testing/imports'));
        file_put_contents($path = $dir.'/template.xlsx', $raw);

        $import = $this->upload(UploadedFile::fake()->createWithContent('template.xlsx', (string) file_get_contents($path)));
        $this->assertSame(ImportStatus::Done, $import->status, json_encode($import->errors) ?: '');
        $this->assertTrue(Employee::where('code', 'E-0101')->exists());
        $this->assertSame([$this->finance->id], Employee::where('code', 'E-0101')->sole()->entityIds());   // FIN: first Department
    }

    public function test_import_needs_the_create_permission(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);

        $this->get(route('hrm.employees.import'))->assertForbidden();
        $this->get(route('hrm.employees.import.template'))->assertForbidden();
        $this->get(route('hrm.employees.index'))->assertDontSee(route('hrm.employees.import'));
    }

    public function test_only_spreadsheets_are_accepted(): void
    {
        Livewire::test(EmployeeImports::class)
            ->set('file', UploadedFile::fake()->create('photo.png', 10, 'image/png'))
            ->call('start')
            ->assertHasErrors(['file' => 'mimes']);
    }
}
