<?php

namespace App\Providers;

use App\Core\Tenancy\IdentifyTenant;
use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire clicks (wire:click, wire:model...) are separate requests to /livewire/update.
        // This re-runs IdentifyTenant on them too, so actions use the same company database as the page.
        Livewire::addPersistentMiddleware([
            IdentifyTenant::class,
        ]);
    }
}
