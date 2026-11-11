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
| 'can' => 'core.users.view' on an item hides it from users without that permission.
| A list ('can' => ['a', 'b']) shows the item when the user has ANY of them.
|
| 'current' => 'hrm.org.*' keeps the item highlighted on several pages (default: its own route).
|
| 'module' => 'hrm' hides the whole group when the company does not have
| that module switched on (Tenant::hasModule). Groups without it always show.
|
*/

return [

    [
        'heading' => 'Self Service',
        'active' => 'ess.*',
        'items' => [
            ['label' => 'My Profile', 'route' => 'ess.profile'],
            ['label' => 'My Attendance', 'route' => 'ess.attendance'],
            ['label' => 'My Team Attendance', 'route' => 'ess.team-attendance'],
            ['label' => 'My Leave Requests', 'route' => null],
            ['label' => 'My Payslips', 'route' => null],
            ['label' => 'My Tax Card', 'route' => null],
            ['label' => 'My Profile Update Requests', 'route' => 'ess.profile-requests'],
            ['label' => 'My Interviews', 'route' => 'ess.interviews'],
        ],
    ],

    [
        'heading' => 'HRM',
        'module' => 'hrm',
        'active' => 'hrm.*',
        'items' => [
            ['label' => 'Employee', 'route' => 'hrm.employees.index', 'current' => 'hrm.employees.*', 'can' => 'hrm.employee.view'],
            // Organization and Recruitment open as tabs (config/sections.php): shown if ANY tab is allowed.
            ['label' => 'Organization', 'route' => 'hrm.organization', 'current' => 'hrm.org.*', 'can' => ['hrm.organization.manage', 'hrm.employee.view']],
            ['label' => 'Recruitment', 'route' => 'hrm.recruitment', 'current' => 'hrm.recruitment*', 'can' => ['hrm.recruitment.request', 'hrm.recruitment.manage', 'hrm.recruitment.setup']],
            ['label' => 'Confirmation', 'route' => null],
            ['label' => 'Training', 'route' => null],
            ['label' => 'Transfer', 'route' => null],
            ['label' => 'Disciplinary Action', 'route' => null],
            ['label' => 'Separation', 'route' => null],
        ],
    ],

    [
        'heading' => 'Payroll',
        'module' => 'payroll',
        'active' => 'payroll.*',
        'items' => [
            // Attendance opens as tabs (config/sections.php). More tabs (rosters, punches …) come in Phase 7.
            ['label' => 'Attendance', 'route' => 'payroll.attendance', 'current' => 'payroll.attendance*', 'can' => ['payroll.roster.manage', 'payroll.punch.manage', 'payroll.attendance.setup']],
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
        'module' => 'workflow',
        'active' => 'workflow.*',
        'items' => [
            ['label' => 'My Approvals', 'route' => 'workflow.inbox'],
            ['label' => 'Workflow Setup', 'route' => 'workflow.setup', 'can' => 'workflow.setup.manage'],
        ],
    ],

    [
        'heading' => 'Setup',
        'module' => 'core',
        'active' => 'core.*',
        'items' => [
            ['label' => 'Company Settings', 'route' => 'core.settings', 'can' => 'core.settings.manage'],
            ['label' => 'Fiscal Years', 'route' => 'core.fiscal-years', 'can' => 'core.fiscal_years.manage'],
            ['label' => 'Currencies', 'route' => 'core.currencies', 'can' => 'core.settings.manage'],
            ['label' => 'Users', 'route' => 'core.users', 'can' => 'core.users.view'],
            ['label' => 'Roles & Permissions', 'route' => 'core.roles', 'can' => 'core.roles.manage'],
            ['label' => 'UI Kit', 'route' => 'core.ui-kit'],
        ],
    ],

];
