<?php

namespace App\Modules\Payroll\Contracts;

use App\Modules\Payroll\Exceptions\DeviceException;
use App\Modules\Payroll\Models\Device;
use Illuminate\Support\Carbon;

/**
 * Talks to one attendance device. The real one is ZktecoClient; tests bind a fake, so no device is needed.
 * (An interface: the sync only knows THAT it can fetch punches, not HOW a ZKTeco speaks.)
 */
interface PunchDeviceClient
{
    /**
     * Every punch stored on the device (devices keep their log; duplicates are skipped when saving).
     *
     * @return list<array{code: string, punched_at: Carbon}> code = the user's ID on the device = employees.access_code
     *
     * @throws DeviceException when the device cannot be reached or answers nonsense
     */
    public function fetch(Device $device): array;
}
