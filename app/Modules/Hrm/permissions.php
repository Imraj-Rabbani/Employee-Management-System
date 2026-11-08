<?php

/*
| Permissions of the HRM module. Group => [key => label]. Keys must start with "hrm.".
| More are added as the HRM screens are built (Phases 4–8).
*/

return [
    'Employees' => [
        'hrm.employee.view' => 'View employees',
        'hrm.employee.create' => 'Add employees',
        'hrm.employee.update' => 'Edit employees',
        'hrm.employee.delete' => 'Delete employees',
        'hrm.employee.login' => 'Give employees a login (invite, link, unlink)',
    ],
    'Organization' => [
        'hrm.organization.manage' => 'Manage org units, positions and grades',
    ],
];
