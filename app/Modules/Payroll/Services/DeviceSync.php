<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Actions\StorePunches;
use App\Modules\Payroll\Contracts\PunchDeviceClient;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Exceptions\DeviceException;
use App\Modules\Payroll\Models\Device;
use Illuminate\Support\Carbon;

/**
 * Pulls one device's punches into punch_logs and records how it went on the device
 * (last_synced_at, last_sync_error). Used by the 5-minute schedule and by "Sync now".
 */
class DeviceSync
{
    /** Punches older than this are not stored again (devices keep months of log). */
    public const KEEP_DAYS = 62;

    public function __construct(private PunchDeviceClient $client, private StorePunches $store) {}

    /** @return int new punches stored; throws DeviceException after recording the error on the device */
    public function sync(Device $device): int
    {
        try {
            $punches = $this->client->fetch($device);
        } catch (DeviceException $e) {
            $device->forceFill(['last_sync_error' => mb_substr($e->getMessage(), 0, 500)])->save();

            throw $e;
        }

        $since = Carbon::now()->subDays(self::KEEP_DAYS)->startOfDay();
        $recent = array_values(array_filter($punches, fn (array $p) => $p['punched_at']->gte($since)));

        $stored = $this->store->handle($recent, PunchSource::Device, $device->id);
        $device->forceFill(['last_synced_at' => now(), 'last_sync_error' => null])->save();

        return $stored;
    }
}
