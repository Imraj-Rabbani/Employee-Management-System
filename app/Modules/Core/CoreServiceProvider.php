<?php

namespace App\Modules\Core;

use App\Core\Permissions\PermissionRegistry;
use App\Core\Support\ModuleServiceProvider;
use App\Models\User;
use App\Modules\Core\Services\Permissions;
use App\Modules\Core\Services\Settings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'core';

    public function register(): void
    {
        // One Settings / Permissions object per request or job ("scoped"), so a queue worker never
        // keeps one company's settings or one user's rights in memory for the next job.
        $this->app->scoped(Settings::class);
        $this->app->scoped(Permissions::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Runs before EVERY Gate check. For company permission keys (from the modules' permissions.php)
        // it gives the final yes/no. For anything else (e.g. the platform's "super-admin" gate, model policies)
        // it returns null = "not my business", so a company Administrator never passes those by accident.
        Gate::before(function (User $user, string $ability) {
            if (! $this->app->make(PermissionRegistry::class)->has($ability)) {
                return null;
            }

            return $this->app->make(Permissions::class)->check($user, $ability);
        });

        // "Set your password" from the invitation email (5.7): NO login, so outside the module's "auth" +
        // "tenant" group. "signed" rejects a URL whose signature is wrong or expired.
        Route::middleware(['web', 'signed', 'throttle:30,1'])
            ->prefix('invitation')
            ->name('invitation.')
            ->group($this->modulePath().'/routes/signed.php');
    }
}
