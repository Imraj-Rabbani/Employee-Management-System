<?php

namespace App\Modules\Payroll\Exceptions;

use RuntimeException;

/** A device could not be read. The message is shown to HR (Devices list, "Sync now"). */
class DeviceException extends RuntimeException {}
