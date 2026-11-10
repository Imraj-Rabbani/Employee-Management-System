<?php

/*
| Permissions of the Payroll module. Group => [key => label]. Keys must start with "payroll.".
| More are added as the Payroll screens are built (Phases 9–12).
*/

return [
    'Attendance' => [
        'payroll.attendance.setup' => 'Manage attendance setup (shifts, weekends, holidays)',
    ],
    'Salaries' => [
        'payroll.salary.view' => 'View salaries',
        'payroll.salary.manage' => 'Set up salaries',
    ],
    'Payroll runs' => [
        'payroll.run.view' => 'View payroll runs',
        'payroll.run.process' => 'Process payroll runs',
        'payroll.run.approve' => 'Approve payroll runs',
    ],
];
