<?php

use App\Modules\Hrm\Exports\TemplateSheet;
use App\Modules\Payroll\Exports\RosterExport;
use App\Modules\Payroll\Exports\SalaryImportTemplate;
use App\Modules\Payroll\Imports\AdjustmentImporter;
use App\Modules\Payroll\Models\PayrollRun;
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

// Payroll → Attendance → Rosters: the list and one roster's page. Only rosters of the entities the user
// can see (Roster::visibleTo): another unit's roster id in the URL gives 404.
Route::middleware('can:payroll.roster.manage')->prefix('attendance/rosters')->name('attendance.rosters')->group(function () {
    Route::view('/', 'payroll::attendance.rosters');
    Route::get('{id}', fn (int $id) => view('payroll::attendance.roster-show', [
        'roster' => Roster::query()->visibleTo(auth()->user())->with('entity')->findOrFail($id),
    ]))->whereNumber('id')->name('.show');
    // The grid as Excel: also the template for "Import from Excel".
    Route::get('{id}/export', function (int $id) {
        $export = new RosterExport(Roster::query()->visibleTo(auth()->user())->findOrFail($id));

        return Excel::download($export, $export->fileName());
    })->whereNumber('id')->name('.export');
});

// Payroll → Attendance → Punches: every punch of the employees the user can see, and CSV upload.
Route::middleware('can:payroll.punch.manage')->prefix('attendance/punches')->name('attendance.punches')->group(function () {
    Route::view('/', 'payroll::attendance.punches');
    Route::get('example', fn () => Excel::download(
        new TemplateSheet('Punches', ['access_code', 'punched_at'], [['1001', now()->format('Y-m-d').' 08:55'], ['1001', now()->format('Y-m-d').' 18:05']]),
        'punches-example.csv',
    ))->name('.template');
});

// Payroll → Attendance → Daily attendance: the processed days, and "Process again" for a date range.
Route::middleware('can:payroll.attendance.process')->get('attendance/days', fn () => view('payroll::attendance.days'))
    ->name('attendance.days');

// Payroll → Attendance → Corrections: corrections of the employees the user can see; ask one for them.
Route::middleware('can:payroll.correction.manage')->get('attendance/corrections', fn () => view('payroll::attendance.corrections'))
    ->name('attendance.corrections');

// Payroll → Attendance → Overtime: overtime requests of the employees the user can see; ask for them.
Route::middleware('can:payroll.overtime.manage')->get('attendance/overtime', fn () => view('payroll::attendance.overtime'))
    ->name('attendance.overtime');

// Payroll → Attendance → Calendar: the team grid, or ?employee=5 for one employee's month.
Route::middleware('can:payroll.attendance.process')->get('attendance/calendar', fn () => view('payroll::attendance.calendar', [
    'employeeId' => request()->integer('employee') ?: null,
]))->name('attendance.calendar');

// Payroll → Attendance setup: one page view, one URL per section, like HRM → Organization.
Route::middleware('can:payroll.attendance.setup')->prefix('attendance')->name('attendance.')->group(function () {
    foreach (['shifts', 'weekends', 'holidays', 'holiday-profiles', 'devices'] as $section) {
        Route::view($section, 'payroll::attendance.setup', ['section' => $section])->name($section);
    }
});

// Payroll → Salary Setup: the sidebar item opens the first tab. One page view, one URL per section.
Route::middleware('can:payroll.salary.manage')->group(function () {
    Route::get('setup', fn () => redirect()->route('payroll.setup.heads'))->name('setup');

    // Excel template for the salary import: lists the employees this user may see.
    Route::get('setup/import/template', fn () => Excel::download(new SalaryImportTemplate, 'salary-import-template.xlsx'))
        ->name('setup.import.template');

    foreach (['heads', 'profiles', 'pay-scales', 'import', 'settings'] as $section) {
        Route::view("setup/{$section}", 'payroll::setup', ['section' => $section])->name("setup.{$section}");
    }
});

// Payroll → Payroll runs: the list of runs (only those the user may see) and the New run form; one run's review page.
Route::middleware('can:payroll.run.view')->prefix('runs')->name('runs')->group(function () {
    Route::get('/', fn () => view('payroll::runs'));
    Route::get('{id}', fn (int $id) => view('payroll::run-show', [
        'run' => PayrollRun::query()->visibleTo(auth()->user())->findOrFail($id),
    ]))->whereNumber('id')->name('.show');
});

// Payroll → Adjustments: one-time earnings / deductions of the employees the user can see, and the import template.
Route::middleware('can:payroll.adjustment.view')->prefix('adjustments')->name('adjustments')->group(function () {
    Route::view('/', 'payroll::adjustments');
    Route::get('template', fn () => Excel::download(
        new TemplateSheet('Adjustments', AdjustmentImporter::COLUMNS, [
            ['1001', 'SHIFT', now()->year, now()->month, '2000', 'Night shift allowance'],
            ['1002', 'CANTEEN', now()->year, now()->month, '500', 'Canteen bill'],
        ]),
        'payroll-adjustments-template.xlsx',
    ))->middleware('can:payroll.adjustment.manage')->name('.template');
});
