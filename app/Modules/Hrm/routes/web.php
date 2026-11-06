<?php

use Illuminate\Support\Facades\Route;

// Prefix "/hrm" and name "hrm." are added by ModuleServiceProvider.
// can:... = the permission needed (permissions.php). Livewire re-checks it on every click (persistent middleware).
Route::view('ping', 'hrm::ping')->name('ping');

// HRM → Organization: one page view, one URL per section (hrm.org.units, hrm.org.grades ...). [ORG-01]
Route::middleware('can:hrm.organization.manage')->prefix('org')->name('org.')->group(function () {
    foreach (['units', 'positions', 'designations', 'grades', 'branches', 'cost-centers', 'employment-types', 'unit-levels'] as $section) {
        Route::view($section, 'hrm::organization', ['section' => $section])->name($section);
    }
});
