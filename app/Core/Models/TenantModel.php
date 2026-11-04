<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every model whose rows belong to one company: employees,
 * attendance, leave, payroll... They live in the tenant database (ems_tenant_{code}),
 * which IdentifyTenant has switched to for the current request.
 *
 *     class Employee extends TenantModel { ... }
 *
 * Central models (Tenant, User) extend Model directly and use the "central" connection.
 */
abstract class TenantModel extends Model
{
    protected $connection = 'tenant';
}
