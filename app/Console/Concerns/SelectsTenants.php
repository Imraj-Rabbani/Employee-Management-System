<?php

namespace App\Console\Concerns;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * For tenants:* commands with a {--tenant=*} option.
 * Gives the tenants to work on: the ones named with --tenant, or all of them.
 *
 * @mixin Command
 */
trait SelectsTenants
{
    /** @return Collection<int, Tenant> */
    protected function selectedTenants(): Collection
    {
        $query = Tenant::query()->orderBy('code');

        if ($codes = $this->option('tenant')) {
            $query->whereIn('code', $codes);
        }

        $list = $query->get();

        if ($list->isEmpty()) {
            $this->components->warn('No tenants found.');
        }

        return $list;
    }
}
