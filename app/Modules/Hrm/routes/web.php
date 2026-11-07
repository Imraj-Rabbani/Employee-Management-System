<?php

use App\Modules\Hrm\Models\Employee;
use Illuminate\Support\Facades\Route;

// Prefix "/hrm" and name "hrm." are added by ModuleServiceProvider.
// can:... = the permission needed (permissions.php). Livewire re-checks it on every click (persistent middleware).
Route::view('ping', 'hrm::ping')->name('ping');

// HRM → Employees. "viewAny" is answered by EmployeePolicy (needs hrm.employee.view). [EMP-05]
Route::view('employees', 'hrm::employees.index')
    ->middleware('can:viewAny,'.Employee::class)
    ->name('employees.index');

// HRM → Organization: one page view, one URL per section (hrm.org.units, hrm.org.grades ...). [ORG-01]
Route::middleware('can:hrm.organization.manage')->prefix('org')->name('org.')->group(function () {
    foreach (['units', 'positions', 'designations', 'grades', 'branches', 'cost-centers', 'employment-types', 'unit-levels'] as $section) {
        Route::view($section, 'hrm::organization', ['section' => $section])->name($section);
    }
});
