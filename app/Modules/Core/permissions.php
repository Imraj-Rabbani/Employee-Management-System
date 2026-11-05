<?php

/*
| Permissions of the Core module ("Setup" in the sidebar). Group => [key => label].
| Keys must start with "core.". Run `php artisan permissions:sync` after removing or renaming a key.
*/

return [
    'Users' => [
        'core.users.view' => 'View users',
        'core.users.manage' => 'Change users\' roles',
    ],
    'Company' => [
        'core.settings.manage' => 'Change company settings',
        'core.fiscal_years.manage' => 'Manage fiscal years',
    ],
    'Roles & Permissions' => [
        'core.roles.manage' => 'Manage roles and their permissions',
    ],
];
