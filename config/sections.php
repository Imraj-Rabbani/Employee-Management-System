<?php

/*
|--------------------------------------------------------------------------
| Section tabs
|--------------------------------------------------------------------------
|
| A "section" is one sidebar item whose pages are shown as tabs, e.g.
| HRM → Recruitment = Manpower requests · Job postings · Applicants · ...
|
| Each tab:
|   'label'   the tab text
|   'route'   where the tab goes
|   'current' route pattern(s) that highlight the tab (default: its own route),
|             e.g. 'hrm.recruitment.requests*' also covers a request's own page
|   'can'     permission needed to see the tab (hidden otherwise)
|
| Grouped per module, so section="hrm.recruitment" means ['hrm']['recruitment'].
| Shown with <x-ui.section-tabs section="hrm.recruitment" />. The sidebar item
| links to the section's route (e.g. hrm.recruitment), which opens the first
| tab the user may see (routes/web.php of the module).
|
*/

return [

    'hrm' => [

        'organization' => [
            'label' => 'Organization',
            'tabs' => [
                ['label' => 'Org units', 'route' => 'hrm.org.units', 'can' => 'hrm.organization.manage'],
                ['label' => 'Org chart', 'route' => 'hrm.org.chart', 'can' => 'hrm.employee.view'],
                ['label' => 'Positions', 'route' => 'hrm.org.positions', 'can' => 'hrm.organization.manage'],
                ['label' => 'Designations', 'route' => 'hrm.org.designations', 'can' => 'hrm.organization.manage'],
                ['label' => 'Grades', 'route' => 'hrm.org.grades', 'can' => 'hrm.organization.manage'],
                ['label' => 'Branches', 'route' => 'hrm.org.branches', 'can' => 'hrm.organization.manage'],
                ['label' => 'Cost centers', 'route' => 'hrm.org.cost-centers', 'can' => 'hrm.organization.manage'],
                ['label' => 'Employment types', 'route' => 'hrm.org.employment-types', 'can' => 'hrm.organization.manage'],
                ['label' => 'Unit levels', 'route' => 'hrm.org.unit-levels', 'can' => 'hrm.organization.manage'],
            ],
        ],

        'recruitment' => [
            'label' => 'Recruitment',
            'tabs' => [
                ['label' => 'Manpower requests', 'route' => 'hrm.recruitment.requests', 'current' => 'hrm.recruitment.requests*', 'can' => 'hrm.recruitment.request'],
                ['label' => 'Job postings', 'route' => 'hrm.recruitment.postings', 'current' => ['hrm.recruitment.postings*', 'hrm.recruitment.offers.*'], 'can' => 'hrm.recruitment.manage'],
                ['label' => 'Applicants', 'route' => 'hrm.recruitment.applicants', 'current' => 'hrm.recruitment.applicants*', 'can' => 'hrm.recruitment.manage'],
                ['label' => 'Reports', 'route' => 'hrm.recruitment.reports.open-positions', 'current' => 'hrm.recruitment.reports.*', 'can' => 'hrm.recruitment.manage'],
                ['label' => 'Setup', 'route' => 'hrm.recruitment.setup.factors', 'current' => 'hrm.recruitment.setup.*', 'can' => 'hrm.recruitment.setup'],
            ],
        ],

    ],

];
