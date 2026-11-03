<?php

namespace App\Modules\Hrm;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HrmServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the Hrm module: its routes and its views.
     */
    public function boot(): void
    {
        // Every Hrm URL starts with /hrm and every route name with "hrm.".
        Route::middleware(['web', 'auth'])
            ->prefix('hrm')
            ->name('hrm.')
            ->group(__DIR__.'/routes/web.php');

        // Lets us write view('hrm::ping') for resources/views/ping.blade.php.
        $this->loadViewsFrom(__DIR__.'/resources/views', 'hrm');
    }
}
