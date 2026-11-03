<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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
        if (file_exists($path.'/routes/web.php')) {
            Route::middleware(['web', 'auth'])
                ->prefix($this->name)
                ->name($this->name.'.')
                ->group($path.'/routes/web.php');
        }

        // Lets us write view('{name}::ping') for resources/views/ping.blade.php.
        $this->loadViewsFrom($path.'/resources/views', $this->name);
    }

    /** The folder of the child class, e.g. app/Modules/Hrm. */
    protected function modulePath(): string
    {
        return dirname((string) (new \ReflectionClass($this))->getFileName());
    }
}
