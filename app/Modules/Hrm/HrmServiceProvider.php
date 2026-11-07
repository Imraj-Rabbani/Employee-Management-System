<?php

namespace App\Modules\Hrm;

use App\Core\Support\ModuleServiceProvider;
use App\Modules\Hrm\Services\EmployeeVisibility;

class HrmServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'hrm';

    public function register(): void
    {
        // One per request or job ("scoped"), like Permissions: never carries one user's org units into the next job.
        $this->app->scoped(EmployeeVisibility::class);
    }
}
