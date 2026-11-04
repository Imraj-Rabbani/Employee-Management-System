<?php

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;

/*
| Global helper functions. Loaded by Composer ("files" in composer.json).
*/

if (! function_exists('tenant')) {
    /** The current tenant (company), or null when none is active, e.g. on the login page. */
    function tenant(): ?Tenant
    {
        return app(TenantManager::class)->current();
    }
}
