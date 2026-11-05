<?php

namespace App\Providers;

use App\Core\Plugins\PluginRegistry;
use App\Core\Tenancy\EnsureModuleEnabled;
use App\Core\Tenancy\IdentifyTenant;
use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\User;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One TenantManager per request or queued job ("scoped"), so a long-running
        // queue worker never carries one company's tenant over into the next job.
        $this->app->scoped(TenantManager::class);

        // The list of tenant migration folders is the same for the whole app: one shared instance.
        $this->app->singleton(TenantMigrationPaths::class);

        // Plugin slots and plugins are declared once by the module providers: one shared instance.
        $this->app->singleton(PluginRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire clicks (wire:click, wire:model...) are separate requests to /livewire/update.
        // This re-runs IdentifyTenant on them too, so actions use the same company database as the page.
        // EnsureModuleEnabled too, so a module's Livewire actions stop working when the module is switched off.
        Livewire::addPersistentMiddleware([
            IdentifyTenant::class,
            EnsureModuleEnabled::class,
            // "password.confirm": pages that asked for the password (e.g. 2FA settings) keep asking once it expires.
            RequirePassword::class,
        ]);

        // Gate "super-admin": used as ->middleware('can:super-admin') on /admin routes and @can('super-admin') in Blade.
        Gate::define('super-admin', fn (User $user) => $user->isSuperAdmin());

        // Storage::disk('tenant'): a local disk inside the current company's own folder.
        Storage::extend('tenant', function (Application $app, array $config) {
            $tenant = tenant() ?? throw new LogicException('Storage::disk(\'tenant\') was used with no tenant active.');

            /** @var FilesystemManager $storage */
            $storage = $app['filesystem'];

            return $storage->createLocalDriver([...$config, 'root' => storage_path('app/tenants/'.$tenant->code)], 'tenant');
        });
    }
}
