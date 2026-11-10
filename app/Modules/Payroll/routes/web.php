<?php

use Illuminate\Support\Facades\Route;

// Prefix "/payroll" and name "payroll." are added by ModuleServiceProvider.
Route::view('ping', 'payroll::ping')->name('ping');

// Payroll → Attendance (sidebar): opens the first tab (config/sections.php) this user may see. 403 when none.
Route::get('attendance', function () {
    foreach (config('sections.payroll.attendance.tabs') as $tab) {
        if (! isset($tab['can']) || auth()->user()->can($tab['can'])) {
            return redirect()->route($tab['route']);
        }
    }
    abort(403);
})->name('attendance');

// Payroll → Attendance setup (7.1): one page view, one URL per section, like HRM → Organization. [TA-01, TA-02]
Route::middleware('can:payroll.attendance.setup')->prefix('attendance')->name('attendance.')->group(function () {
    foreach (['shifts', 'weekends', 'holidays', 'holiday-profiles'] as $section) {
        Route::view($section, 'payroll::attendance.setup', ['section' => $section])->name($section);
    }
});
