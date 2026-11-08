<?php

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Support\Facades\Route;

// Prefix "/hrm" and name "hrm." are added by ModuleServiceProvider.
// can:... = the permission needed (permissions.php). Livewire re-checks it on every click (persistent middleware).
Route::view('ping', 'hrm::ping')->name('ping');

// HRM → Employees. "viewAny" is answered by EmployeePolicy (needs hrm.employee.view). [EMP-05]
Route::view('employees', 'hrm::employees.index')
    ->middleware('can:viewAny,'.Employee::class)
    ->name('employees.index');

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
})->middleware('can:view,employee')->name('employees.show');

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
