<?php

namespace App\Modules\Payroll\Exceptions;

use RuntimeException;

/** A salary profile cannot be applied (no basic head, gross too small ...). The message is shown to HR. */
class SalaryProfileException extends RuntimeException {}
