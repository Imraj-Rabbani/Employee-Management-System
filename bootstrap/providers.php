<?php

use App\Modules\Core\CoreServiceProvider;
use App\Modules\Hrm\HrmServiceProvider;
use App\Modules\Payroll\PayrollServiceProvider;
use App\Modules\Workflow\WorkflowServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FolioServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    FolioServiceProvider::class,
    VoltServiceProvider::class,

    // Modules (app/Modules)
    CoreServiceProvider::class,
    WorkflowServiceProvider::class,
    HrmServiceProvider::class,
    PayrollServiceProvider::class,
];
