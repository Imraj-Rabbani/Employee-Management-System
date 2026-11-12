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
    'Recruitment' => [
        'hrm.recruitment.request' => 'Create manpower requests (for the org units they can see)',
        'hrm.recruitment.manage' => 'Manage job postings and applicants (HR)',
        'hrm.recruitment.setup' => 'Manage recruitment setup (interview factors, rejection reasons)',
    ],
    'Leave' => [
        'hrm.leave.setup' => 'Manage leave setup (leave types, leave profiles)',
    ],
];
