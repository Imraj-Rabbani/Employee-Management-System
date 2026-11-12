<?php

namespace App\Modules\Payroll;

use App\Core\Plugins\PluginRegistry;
use App\Core\Support\ModuleServiceProvider;
use App\Modules\Payroll\Console\ProcessAttendanceCommand;
use App\Modules\Payroll\Console\SyncDevicePunches;
use App\Modules\Hrm\Contracts\WorkCalendar;
use App\Modules\Payroll\Contracts\LeaveCalendar;
use App\Modules\Payroll\Contracts\PunchDeviceClient;
use App\Modules\Payroll\Services\AttendanceWorkCalendar;
use App\Modules\Payroll\Services\NoLeaveCalendar;
use App\Modules\Payroll\Services\ZktecoClient;
use Illuminate\Support\Facades\Route;

class PayrollServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'payroll';

    public function register(): void
    {
        // The sync asks for "a device client"; this says which one. Tests swap in a fake.
        $this->app->bind(PunchDeviceClient::class, ZktecoClient::class);
        // Approved leave for attendance processing. bindIf: HRM binds the real one (ApprovedLeaveCalendar, 8.4),
        // whichever provider registers first.
        $this->app->bindIf(LeaveCalendar::class, NoLeaveCalendar::class);
        // Days off for leave counting (8.3): the HRM leave rules ask, the attendance setup answers.
        $this->app->bind(WorkCalendar::class, AttendanceWorkCalendar::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncDevicePunches::class, ProcessAttendanceCommand::class]);
        }

        // Self Service pages of this module (My Attendance, 7.3e), under /ess like HRM's.
        Route::middleware(['web', 'auth', 'tenant', 'tenant.settings', 'module:payroll'])
            ->prefix('ess')
            ->name('ess.')
            ->group($this->modulePath().'/routes/ess.php');

        // Extension points a company can customise (Architecture §8). Plugins are added in Phases 11–12, e.g.
        // $plugins->add('tax', 'TaxBangladesh', 'Bangladesh', Plugins\TaxBangladesh\BangladeshTaxCalculator::class);
        $plugins = $this->app->make(PluginRegistry::class);
        $plugins->slot('tax', 'Tax calculator', module: 'payroll');
        $plugins->slot('bank_file', 'Bank file format', module: 'payroll');
        $plugins->slot('payroll_stages', 'Extra payroll stages', multiple: true, module: 'payroll');
    }
}
