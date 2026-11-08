<?php

namespace App\Modules\Hrm;

use App\Core\Support\ModuleServiceProvider;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Support\Facades\Route;

class HrmServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'hrm';

    public function register(): void
    {
        // One per request or job ("scoped"), like Permissions: never carries one user's org units into the next job.
        $this->app->scoped(EmployeeVisibility::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Self Service (5.8+): an employee's own pages. Same protection as module pages, but under
        // /ess and route names "ess.*" (the sidebar's Self Service group opens on "ess.*").
        Route::middleware(['web', 'auth', 'tenant', 'tenant.settings', 'module:hrm'])
            ->prefix('ess')
            ->name('ess.')
            ->group($this->modulePath().'/routes/ess.php');
    }
}
