<?php

namespace App\Modules\Hrm\Data;

/**
 * One reason a leave request is refused. $code for code and tests ('balance', 'notice' …), $field for
 * the form input the message belongs under, $message for the person.
 */
final readonly class LeaveProblem
{
    public function __construct(
        public string $code,
        public string $field,
        public string $message,
    ) {}
}
