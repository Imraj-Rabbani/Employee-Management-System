<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Base class for every model whose rows belong to one company: employees,
 * attendance, leave, payroll... They live in the tenant database (ems_tenant_{code}),
 * which IdentifyTenant has switched to for the current request.
 *
 *     class Employee extends TenantModel { ... }
 *
 * Every create, update and delete is written to the company's activity log (who, when, old → new). [FND-06]
 * Leave sensitive or noisy columns out with:    protected array $auditExcept = ['photo_blob'];
 * Hidden attributes ($hidden) and timestamps are never logged.
 *
 * Central models (Tenant, User) extend Model directly and use the "central" connection.
 */
abstract class TenantModel extends Model
{
    use LogsActivity;

    protected $connection = 'tenant';

    /** @var list<string> extra columns to leave out of the activity log */
    protected array $auditExcept = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept([...$this->getHidden(), ...$this->auditExcept, 'id', 'created_at', 'updated_at'])
            ->logOnlyDirty()          // on update: only the columns that really changed
            ->dontLogEmptyChanges();  // a save that changed nothing writes no entry
    }
}
