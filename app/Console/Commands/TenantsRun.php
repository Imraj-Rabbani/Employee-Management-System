<?php

namespace App\Console\Commands;

use App\Console\Concerns\SelectsTenants;
use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Runs any artisan command once per company, with that company's database
 * as the default connection. Put the command in quotes when it has options:
 *
 *     php artisan tenants:run "db:seed --class=LeaveTypeSeeder"
 *     php artisan tenants:run "payroll:recalculate" --tenant=demo
 */
class TenantsRun extends Command
{
    use SelectsTenants;

    protected $signature = 'tenants:run
                            {line : The command to run, in quotes, e.g. "db:seed --class=X"}
                            {--tenant=* : Only these tenant codes (default: all tenants)}';

    protected $description = 'Run an artisan command for each tenant';

    /**
     * Commands that must not go through tenants:run.
     * migrate* would run the CENTRAL migrations (database/migrations) in a company database;
     * tenants:* already loop over the companies themselves.
     */
    private const BLOCKED = ['migrate*', 'db:wipe', 'tenants:*'];

    public function handle(TenantManager $tenants, TenantDatabase $databases): int
    {
        $line = trim((string) $this->argument('line'));
        $name = Str::before($line, ' ');

        if (! array_key_exists($name, Artisan::all())) {
            $this->components->error("Command [{$name}] does not exist.");

            return self::FAILURE;
        }

        if (Str::is(self::BLOCKED, $name)) {
            $this->components->error("[{$name}] cannot be run with tenants:run. Use tenants:migrate / tenants:seed instead.");

            return self::FAILURE;
        }

        $list = $this->selectedTenants();
        if ($list->isEmpty()) {
            return self::FAILURE;
        }

        $central = DB::getDefaultConnection();
        $result = self::SUCCESS;

        foreach ($list as $tenant) {
            $this->components->info("Tenant [{$tenant->code}] {$tenant->name} → {$line}");

            if (! $databases->exists($tenant)) {
                $this->components->error('Database does not exist. Run tenants:migrate first.');
                $result = self::FAILURE;

                continue;
            }

            $tenants->switchTo($tenant);
            DB::setDefaultConnection('tenant');

            try {
                // A string is parsed like a command line, so its options work as typed.
                if (Artisan::call($line, [], $this->getOutput()) !== self::SUCCESS) {
                    $result = self::FAILURE;
                }
            } finally {
                // Always go back to central, even if the command throws an error.
                DB::setDefaultConnection($central);
            }
        }

        $tenants->forget();

        return $result;
    }
}
