<?php

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\InterviewPanelist;
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

// My Interviews (6.6): the panels this employee sits on, with their score sheets.
Route::view('interviews', 'hrm::ess.interviews')->name('interviews');

// An interviewer may download the CV of an applicant THEY interview: the panel seat must be their own.
Route::get('interviews/{panelist}/cv', function (InterviewPanelist $panelist, TenantUploads $uploads) {
    $mine = Employee::query()->where('user_id', auth()->id())->value('id');
    abort_unless($mine !== null && $panelist->employee_id === $mine, 403);

    $applicant = $panelist->interview->application->applicant;
    abort_if($applicant->cv_path === null || ! $uploads->exists($applicant->cv_path), 404);

    return $uploads->download($applicant->cv_path, $applicant->cv_name);
})->name('interviews.cv');
