<?php

/*
| Permissions of the Payroll module. Group => [key => label]. Keys must start with "payroll.".
| More are added as new Payroll screens are built.
*/

return [
    'Attendance' => [
        'payroll.attendance.setup' => 'Manage attendance setup (shifts, weekends, holidays)',
        'payroll.roster.manage' => 'Plan rosters (for the entities they can see)',
        'payroll.punch.manage' => 'View and upload punches (for the employees they can see)',
        'payroll.correction.manage' => 'Ask attendance corrections for the employees they can see',
        'payroll.overtime.manage' => 'Ask overtime for the employees they can see',
        'payroll.attendance.process' => 'View daily attendance and the calendar, and process it again (for the employees they can see)',
    ],
    'Salaries' => [
        'payroll.salary.view' => 'View salaries',
        'payroll.salary.manage' => 'Set up salaries',
    ],
    'Payroll runs' => [
        'payroll.run.view' => 'View payroll runs',
        'payroll.run.process' => 'Process payroll runs',
        'payroll.run.approve' => 'Approve payroll runs',
        'payroll.adjustment.view' => 'View payroll adjustments (of the employees they can see)',
        'payroll.adjustment.manage' => 'Enter, import and withdraw payroll adjustments (of the employees they can see)',
    ],
];
