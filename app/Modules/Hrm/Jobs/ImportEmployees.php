<?php

namespace App\Modules\Hrm\Jobs;

use App\Core\Tenancy\TenantAwareJob;
use App\Modules\Hrm\Imports\EmployeeImporter;
use App\Modules\Hrm\Models\EmployeeImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs an employee Excel import in the background (5.6). [EMP-06]
 *
 * QUEUED: dispatch() only writes the job into the `jobs` table and the upload page answers at once.
 * A separate process, the queue WORKER (`php artisan queue:work`, started by `composer dev`), picks it
 * up and calls handle(). The page polls the EmployeeImport row to show progress.
 *
 * TenantAwareJob: the worker serves every company, so the job carries its company's id and the
 * TenantAware middleware switches to that database before handle().
 */
class ImportEmployees implements ShouldQueue
{
    use Queueable;
    use TenantAwareJob;

    /** No automatic retries: a second run would only repeat the same checks. */
    public int $tries = 1;

    /** Big files may take a while. */
    public int $timeout = 600;

    public function __construct(public int $importId)
    {
        $this->rememberTenant();
    }

    public function handle(EmployeeImporter $importer): void
    {
        $import = EmployeeImport::find($this->importId);

        if ($import !== null) {
            $importer->run($import);
        }
    }
}
