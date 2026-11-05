<?php

use App\Modules\Core\CoreServiceProvider;
use App\Modules\Hrm\HrmServiceProvider;
use App\Modules\Payroll\PayrollServiceProvider;
use App\Modules\Workflow\WorkflowServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FolioServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    CoreServiceProvider::class,
    HrmServiceProvider::class,
    PayrollServiceProvider::class,
    WorkflowServiceProvider::class,
    AppServiceProvider::class,
    FolioServiceProvider::class,
    FortifyServiceProvider::class,
    VoltServiceProvider::class,
];
