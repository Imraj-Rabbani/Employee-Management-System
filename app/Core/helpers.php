<?php

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Modules\Core\Services\Settings;

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

if (! function_exists('settings')) {
    /**
     * The current company's settings.
     *   settings()                       the Settings service (->set(), ->forget(), ->all())
     *   settings('timezone', 'UTC')      one value, with a default
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}
