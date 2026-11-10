<?php

namespace App\Modules\Payroll\Console;

use App\Modules\Payroll\Exceptions\DeviceException;
use App\Modules\Payroll\Models\Device;
use App\Modules\Payroll\Services\DeviceSync;
use Illuminate\Console\Command;

/**
 * Pulls punches from the company's active devices. Runs for ONE company, so call it through tenants:run:
 *
 *     php artisan tenants:run payroll:sync-devices              every company (the scheduler does this every 5 minutes)
 *     php artisan tenants:run payroll:sync-devices --tenant=demo
 *
 * A device that fails is reported and skipped; the others still sync. [TA-04]
 */
class SyncDevicePunches extends Command
{
    protected $signature = 'payroll:sync-devices {--device=* : Only these device ids}';

    protected $description = 'Pull punches from the attendance devices of the current company';

    public function handle(DeviceSync $sync): int
    {
        if (! tenant()) {
            $this->components->error('No company selected. Run it as: php artisan tenants:run payroll:sync-devices');

            return self::FAILURE;
        }
        if (! tenant()->hasModule('payroll')) {
            $this->components->info('Payroll is switched off for this company: nothing to sync.');

            return self::SUCCESS;
        }

        $devices = Device::query()->where('is_active', true)
            ->when($this->option('device'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->get();

        $result = self::SUCCESS;
        foreach ($devices as $device) {
            try {
                $stored = $sync->sync($device);
                $this->components->info("{$device->name}: {$stored} new punches.");
            } catch (DeviceException $e) {
                $this->components->error("{$device->name}: {$e->getMessage()}");
                $result = self::FAILURE;
            }
        }

        return $result;
    }
}
