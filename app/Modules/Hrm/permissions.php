<?php

/*
| Permissions of the HRM module. Group => [key => label]. Keys must start with "hrm.".
| More are added as new HRM screens are built.
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
        'hrm.organization.manage' => 'Manage entities, positions and grades',
    ],
    'Recruitment' => [
        'hrm.recruitment.request' => 'Create manpower requests (for the entities they can see)',
        'hrm.recruitment.manage' => 'Manage job postings and applicants (HR)',
        'hrm.recruitment.setup' => 'Manage recruitment setup (interview factors, rejection reasons)',
    ],
    'Leave' => [
        'hrm.leave.manage' => 'Apply for and withdraw leave for the employees they can see (HR)',
        'hrm.leave.setup' => 'Manage leave setup (leave types, leave profiles)',
        'hrm.leave.balance' => 'Enter opening leave balances and corrections (for the employees they can see)',
    ],
];
