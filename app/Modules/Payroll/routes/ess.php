<?php

use Illuminate\Support\Facades\Route;

// Self Service pages of the Payroll module. Prefix "/ess" and name "ess." come from PayrollServiceProvider.
// No permission: every user sees only their OWN data (the page finds their employee record).

// My Attendance (7.3e): web punch with location, and my recent punches. [TA-04]
Route::view('attendance', 'payroll::ess.attendance')->name('attendance');

// My Team Attendance (7.8): the people below me in the supervisor chain; ?employee=5 for one of them. [TA-08, ESS-04]
Route::get('team-attendance', fn () => view('payroll::ess.team-attendance', [
    'employeeId' => request()->integer('employee') ?: null,
]))->name('team-attendance');
