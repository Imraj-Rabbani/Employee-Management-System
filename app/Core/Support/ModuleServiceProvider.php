<?php

namespace App\Core\Support;

use App\Core\Tenancy\TenantMigrationPaths;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Shared boot logic for every module in app/Modules.
 * A module provider only has to set $name, e.g. "hrm".
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Used for the URL prefix, route names and view namespace. */
    protected string $name;

    public function boot(): void
    {
        $path = $this->modulePath();

        // Every URL starts with /{name} and every route name with "{name}.".
        // Module pages need a logged-in user, their company ("tenant"),
        // and the module switched on for that company ("module:hrm"; core is always on).
        if (file_exists($path.'/routes/web.php')) {
            Route::middleware(['web', 'auth', 'tenant', 'module:'.$this->name])
                ->prefix($this->name)
                ->name($this->name.'.')
                ->group($path.'/routes/web.php');
        }

        // Lets us write view('{name}::ping') for resources/views/ping.blade.php.
        $this->loadViewsFrom($path.'/resources/views', $this->name);

        // Module tables belong to each company, so Database/Migrations holds TENANT migrations.
        // They run with `php artisan tenants:migrate`, not with the central `php artisan migrate`.
        $this->app->make(TenantMigrationPaths::class)->add($path.'/Database/Migrations');

        // Lets us write <livewire:{name}::ping /> for Livewire/Ping.php.
        Livewire::addNamespace(
            $this->name,
            classNamespace: (new \ReflectionClass($this))->getNamespaceName().'\\Livewire',
            classPath: $path.'/Livewire',
            classViewPath: $path.'/resources/views/livewire',
        );
    }

    /** The folder of the child class, e.g. app/Modules/Hrm. */
    protected function modulePath(): string
    {
        return dirname((string) (new \ReflectionClass($this))->getFileName());
    }
}
