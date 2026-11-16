<?php

namespace App\Modules\Payroll\Jobs;

use App\Core\Tenancy\TenantAwareJob;
use App\Modules\Payroll\Imports\SalaryImporter;
use App\Modules\Payroll\Models\SalaryImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Runs a salary Excel import in the background (9.6), like Hrm\Jobs\ImportEmployees. Needs a queue worker. */
class ImportSalaries implements ShouldQueue
{
    use Queueable;
    use TenantAwareJob;

    /** No automatic retries: a second run would only repeat the same checks. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $importId)
    {
        $this->rememberTenant();
    }

    public function handle(SalaryImporter $importer): void
    {
        $import = SalaryImport::find($this->importId);

        if ($import !== null) {
            $importer->run($import);
        }
    }
}
