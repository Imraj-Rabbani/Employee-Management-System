<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Contracts\PunchDeviceClient;
use App\Modules\Payroll\Exceptions\DeviceException;
use App\Modules\Payroll\Models\Device;
use Illuminate\Support\Carbon;
use Rats\Zkteco\Lib\ZKTeco;

/**
 * Reads punches from a ZKTeco device with the rats/zkteco library (UDP, port 4370), like legacy
 * SyncAllDevices. The library has no comm-key support: devices must not have a communication password.
 */
class ZktecoClient implements PunchDeviceClient
{
    /** One slow or switched-off device must not hold up the others. */
    private const TIMEOUT_SECONDS = 15;

    public function fetch(Device $device): array
    {
        if (! defined('ZK_LIB_LOG')) {
            define('ZK_LIB_LOG', storage_path('logs/zkteco.log'));   // the library's own log, outside vendor/
        }

        $zk = new ZKTeco($device->ip, $device->port);
        @socket_set_option($zk->_zkclient, SOL_SOCKET, SO_RCVTIMEO, ['sec' => self::TIMEOUT_SECONDS, 'usec' => 0]);

        if (! $zk->connect()) {
            throw new DeviceException(__('Cannot connect to :ip:port.', ['ip' => $device->ip, 'port' => $device->port]));
        }

        try {
            $raw = $zk->getAttendance();
        } finally {
            $zk->disconnect();
        }

        $punches = [];
        foreach ($raw as $row) {
            // The user's ID on the device; strip non-printable bytes some firmware sends.
            $code = preg_replace('/[^\x20-\x7E]/', '', (string) ($row['id'] ?? '')) ?? '';
            if ($code === '' || empty($row['timestamp'])) {
                continue;
            }
            $punches[] = ['code' => trim($code), 'punched_at' => Carbon::parse($row['timestamp'])];
        }

        return $punches;
    }
}
