<?php

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Exports\EmployeeImportTemplate;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

// Prefix "/hrm" and name "hrm." are added by ModuleServiceProvider.
// can:... = the permission needed (permissions.php). Livewire re-checks it on every click (persistent middleware).
Route::view('ping', 'hrm::ping')->name('ping');

// HRM → Employees. "viewAny" is answered by EmployeePolicy (needs hrm.employee.view). [EMP-05]
Route::view('employees', 'hrm::employees.index')
    ->middleware('can:viewAny,'.Employee::class)
    ->name('employees.index');

// Excel import of new employees (5.6). Must come BEFORE employees/{employee}, or "import" would be taken
// for an employee id. "create" = EmployeePolicy::create. [EMP-06]
Route::middleware('can:create,'.Employee::class)->group(function () {
    Route::view('employees/import', 'hrm::employees.import')->name('employees.import');
    Route::get('employees/import/template', fn (EmployeeVisibility $visibility) => Excel::download(
        new EmployeeImportTemplate($visibility->orgUnitOptionsFor(auth()->user())),
        'employee-import-template.xlsx',
    ))->name('employees.import.template');
});

// One employee's profile. {employee} = ROUTE MODEL BINDING: Laravel loads Employee::find($id) for us
// (through the VisibleToCurrentUser scope, so someone you may not see gives 404). "view" = EmployeePolicy::view.
// ?tab=job picks the tab, so every tab has its own bookmarkable URL. [EMP-01]
Route::get('employees/{employee}', function (Employee $employee) {
    $tabs = [
        'personal' => __('Personal'),
        'job' => __('Job'),
        'family' => __('Family & Nominees'),
        'education' => __('Education'),
        'work-history' => __('Work History'),
        'documents' => __('Documents'),
        'bank' => __('Bank'),
        'history' => __('History'),
    ];
    $tab = array_key_exists((string) request()->query('tab'), $tabs) ? (string) request()->query('tab') : 'personal';

    return view('hrm::employees.show', compact('employee', 'tabs', 'tab'));
})->middleware('can:view,employee')->whereNumber('employee')->name('employees.show');

// HRM → Org Chart: reporting lines (people) or the position tree. [ORG-02]
Route::view('org-chart', 'hrm::org-chart')
    ->middleware('can:viewAny,'.Employee::class)
    ->name('org-chart');

// HRM → Organization: one page view, one URL per section (hrm.org.units, hrm.org.grades ...). [ORG-01]
Route::middleware('can:hrm.organization.manage')->prefix('org')->name('org.')->group(function () {
    foreach (['units', 'positions', 'designations', 'grades', 'branches', 'cost-centers', 'employment-types', 'unit-levels'] as $section) {
        Route::view($section, 'hrm::organization', ['section' => $section])->name($section);
    }
});

// The employee's photo. Photos are private (tenant disk, not /public), so the file is only sent to
// someone who may view this employee. 404 when there is no photo (the page then shows initials).
Route::get('employees/{employee}/photo', function (Employee $employee, TenantUploads $uploads) {
    abort_if($employee->photo_path === null || ! $uploads->exists($employee->photo_path), 404);

    return $uploads->show($employee->photo_path);
})->middleware('can:view,employee')->name('employees.photo');

// An attachment of a profile row (education certificate, experience letter ...). {section} must be listed
// in Employee::ATTACHMENT_SECTIONS, and the row is looked up THROUGH the employee, so neither another table
// nor another employee's file can be reached. Downloads under the name the file had when uploaded.
Route::get('employees/{employee}/attachments/{section}/{id}', function (Employee $employee, string $section, int $id, TenantUploads $uploads) {
    $relation = Employee::ATTACHMENT_SECTIONS[$section] ?? abort(404);
    $row = $employee->{$relation}()->findOrFail($id);
    abort_if($row->attachment_path === null || ! $uploads->exists($row->attachment_path), 404);

    return $uploads->download($row->attachment_path, $row->attachment_name);
})->middleware('can:view,employee')->whereNumber('id')->name('employees.attachment');

// HRM → Recruitment → Manpower requests (6.2): the start of the hiring process. Each user sees the
// requests of the org units they can see (ManpowerRequest::visibleTo). [REC-01]
Route::view('recruitment/requests', 'hrm::recruitment.requests')
    ->middleware('can:hrm.recruitment.request')
    ->name('recruitment.requests');

// One request: details, approval timeline, Edit / Send for approval. Looked up THROUGH visibleTo(), so a
// request of an org unit the user cannot see gives 404 (not a plain ->find()).
Route::get('recruitment/requests/{id}', fn (int $id) => view('hrm::recruitment.request-show', [
    'request' => ManpowerRequest::query()->visibleTo(auth()->user())->findOrFail($id),
]))->middleware('can:hrm.recruitment.request')->whereNumber('id')->name('recruitment.requests.show');

// HRM → Recruitment → Setup: interview factors and rejection reasons (6.1). Same idea as Organization:
// one page view, one URL per section. [REC-04, REC-05]
Route::middleware('can:hrm.recruitment.setup')->prefix('recruitment/setup')->name('recruitment.setup.')->group(function () {
    foreach (['factors', 'reasons'] as $section) {
        Route::view($section, 'hrm::recruitment.setup', ['section' => $section])->name($section);
    }
});
