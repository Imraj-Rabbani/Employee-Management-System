<?php

namespace App\Modules\Payroll;

use App\Core\Plugins\PluginRegistry;
use App\Core\Support\ModuleServiceProvider;

class PayrollServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'payroll';

    public function boot(): void
    {
        parent::boot();

        // Extension points a company can customise (Architecture §8). Plugins are added in Phases 11–12, e.g.
        // $plugins->add('tax', 'TaxBangladesh', 'Bangladesh', Plugins\TaxBangladesh\BangladeshTaxCalculator::class);
        $plugins = $this->app->make(PluginRegistry::class);
        $plugins->slot('tax', 'Tax calculator', module: 'payroll');
        $plugins->slot('bank_file', 'Bank file format', module: 'payroll');
        $plugins->slot('payroll_stages', 'Extra payroll stages', multiple: true, module: 'payroll');
    }
}
