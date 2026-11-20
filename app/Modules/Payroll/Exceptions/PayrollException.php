<?php

namespace App\Modules\Payroll\Exceptions;

use RuntimeException;

/**
 * A payroll calculation that cannot go on for ONE employee, with a message written for HR ("No salary on 31 Dec 2026").
 * The employee's row in the run is marked Failed with this message; the other employees carry on.
 */
class PayrollException extends RuntimeException {}
