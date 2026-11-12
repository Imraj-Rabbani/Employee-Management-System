<?php

namespace App\Modules\Hrm;

use App\Core\Support\ModuleServiceProvider;
use App\Models\User;
use App\Modules\Hrm\Console\AccrueLeaveCommand;
use App\Modules\Hrm\Console\CarryForwardLeaveCommand;
use App\Modules\Hrm\Contracts\WorkCalendar;
use App\Modules\Hrm\Services\ApprovedLeaveCalendar;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Hrm\Services\NoWorkCalendar;
use App\Modules\Payroll\Contracts\LeaveCalendar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class HrmServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'hrm';

    public function register(): void
    {
        // One per request or job ("scoped"), like Permissions: never carries one user's org units into the next job.
        $this->app->scoped(EmployeeVisibility::class);

        // Days off for leave counting (8.3). bindIf: the Payroll module binds the real one (attendance setup),
        // whichever provider registers first.
        $this->app->bindIf(WorkCalendar::class, NoWorkCalendar::class);

        // Approved leave for attendance processing (Payroll asks, 8.4).
        $this->app->bind(LeaveCalendar::class, ApprovedLeaveCalendar::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Nightly leave jobs (8.5), scheduled in routes/console.php.
        if ($this->app->runningInConsole()) {
            $this->commands([AccrueLeaveCommand::class, CarryForwardLeaveCommand::class]);
        }

        // A combined ability (not a permission key, so Gate::before leaves it to this definition):
        // manpower requests can be READ by the managers who ask (request) and by HR who advertise them (manage).
        // Changing or sending a request still needs hrm.recruitment.request.
        Gate::define('hrm.recruitment.view-requests', fn (User $user) => $user->can('hrm.recruitment.request') || $user->can('hrm.recruitment.manage'));

        // Self Service (5.8+): an employee's own pages. Same protection as module pages, but under
        // /ess and route names "ess.*" (the sidebar's Self Service group opens on "ess.*").
        Route::middleware(['web', 'auth', 'tenant', 'tenant.settings', 'module:hrm'])
            ->prefix('ess')
            ->name('ess.')
            ->group($this->modulePath().'/routes/ess.php');

        // "Complete your registration" from an Employees → Invite user email. NO login (they have no
        // password yet), so outside the "auth" + "tenant" group. "signed" rejects a wrong or expired URL.
        Route::middleware(['web', 'signed', 'throttle:30,1'])
            ->prefix('join')
            ->name('join.')
            ->group($this->modulePath().'/routes/join.php');
    }
}
