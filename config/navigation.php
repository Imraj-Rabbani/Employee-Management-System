<?php

/*
|--------------------------------------------------------------------------
| Sidebar navigation
|--------------------------------------------------------------------------
|
| Each group is one expandable section in the sidebar. Clicking its heading
| shows or hides its items. A group opens by itself when the current page
| matches its "active" route pattern.
|
| An item with 'route' => null has no page yet and is shown as "Soon".
| When the screen is built, put its route name here, e.g. 'hrm.employees.index'.
|
*/

return [

    [
        'heading' => 'Self Service',
        'active' => 'ess.*',
        'items' => [
            ['label' => 'My Profile', 'route' => null],
            ['label' => 'My Attendance', 'route' => null],
            ['label' => 'My Leave Requests', 'route' => null],
            ['label' => 'My Payslips', 'route' => null],
            ['label' => 'My Tax Card', 'route' => null],
            ['label' => 'My Profile Update Requests', 'route' => null],
        ],
    ],

    [
        'heading' => 'HRM',
        'active' => 'hrm.*',
        'items' => [
            ['label' => 'Employees', 'route' => null],
            ['label' => 'Organization', 'route' => null],
            ['label' => 'Attendance', 'route' => null],
            ['label' => 'Leave', 'route' => null],
            ['label' => 'Confirmation', 'route' => null],
            ['label' => 'Promotion', 'route' => null],
            ['label' => 'Transfer', 'route' => null],
            ['label' => 'Separation', 'route' => null],
            ['label' => 'Letters & Certificates', 'route' => null],
            ['label' => 'Recruitment', 'route' => null],
            ['label' => 'Training', 'route' => null],
        ],
    ],

    [
        'heading' => 'Payroll',
        'active' => 'payroll.*',
        'items' => [
            ['label' => 'Salary Setup', 'route' => null],
            ['label' => 'Employee Salaries', 'route' => null],
            ['label' => 'Payroll Runs', 'route' => null],
            ['label' => 'Increments', 'route' => null],
            ['label' => 'Incentives', 'route' => null],
            ['label' => 'Tax', 'route' => null],
            ['label' => 'Loans', 'route' => null],
            ['label' => 'Disbursement', 'route' => null],
            ['label' => 'Reports', 'route' => null],
        ],
    ],

    [
        'heading' => 'Workflow',
        'active' => 'workflow.*',
        'items' => [
            ['label' => 'My Approvals', 'route' => null],
            ['label' => 'Workflow Setup', 'route' => null],
        ],
    ],

    [
        'heading' => 'Setup',
        'active' => 'core.*',
        'items' => [
            ['label' => 'Company Settings', 'route' => null],
            ['label' => 'Fiscal Years', 'route' => null],
            ['label' => 'Roles & Permissions', 'route' => null],
            ['label' => 'UI Kit', 'route' => 'core.ui-kit'],
        ],
    ],

];
