<?php

use App\Modules\Hrm\Exports\TemplateSheet;
use App\Modules\Payroll\Exports\RosterExport;
use App\Modules\Payroll\Models\Roster;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

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

// Payroll → Attendance → Rosters (7.2): the list and one roster's page. Only rosters of the org units the user
// can see (Roster::visibleTo): another unit's roster id in the URL gives 404. [TA-03]
Route::middleware('can:payroll.roster.manage')->prefix('attendance/rosters')->name('attendance.rosters')->group(function () {
    Route::view('/', 'payroll::attendance.rosters');
    Route::get('{id}', fn (int $id) => view('payroll::attendance.roster-show', [
        'roster' => Roster::query()->visibleTo(auth()->user())->with('orgUnit')->findOrFail($id),
    ]))->whereNumber('id')->name('.show');
    // The grid as Excel (7.2e): also the template for "Import from Excel".
    Route::get('{id}/export', function (int $id) {
        $export = new RosterExport(Roster::query()->visibleTo(auth()->user())->findOrFail($id));

        return Excel::download($export, $export->fileName());
    })->whereNumber('id')->name('.export');
});

// Payroll → Attendance → Punches (7.3): every punch of the employees the user can see, and CSV upload. [TA-04]
Route::middleware('can:payroll.punch.manage')->prefix('attendance/punches')->name('attendance.punches')->group(function () {
    Route::view('/', 'payroll::attendance.punches');
    Route::get('example', fn () => Excel::download(
        new TemplateSheet('Punches', ['access_code', 'punched_at'], [['1001', now()->format('Y-m-d').' 08:55'], ['1001', now()->format('Y-m-d').' 18:05']]),
        'punches-example.csv',
    ))->name('.template');
});

// Payroll → Attendance setup (7.1): one page view, one URL per section, like HRM → Organization. [TA-01, TA-02]
Route::middleware('can:payroll.attendance.setup')->prefix('attendance')->name('attendance.')->group(function () {
    foreach (['shifts', 'weekends', 'holidays', 'holiday-profiles', 'devices'] as $section) {
        Route::view($section, 'payroll::attendance.setup', ['section' => $section])->name($section);
    }
});
