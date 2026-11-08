<?php

use App\Modules\Hrm\Models\Employee;
use Illuminate\Support\Facades\Route;

// Prefix "/ess" and name "ess." come from HrmServiceProvider. No permission needed: everyone may see
// their OWN record; pages show a message when the login is not linked to an employee (5.7).

// My Profile = the normal profile page of the employee linked to this login.
Route::get('profile', function () {
    $mine = Employee::query()->where('user_id', auth()->id())->first();

    return $mine
        ? redirect()->route('hrm.employees.show', $mine)
        : view('hrm::ess.not-linked');
})->name('profile');

Route::view('profile-requests', 'hrm::ess.profile-requests')->name('profile-requests');
