<?php

namespace App\Modules\Core\Database\Seeders;

use App\Modules\Core\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Every company starts with an Administrator role (passes every permission check, task 2.9c).
 * Safe to run twice.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        if (Role::administrator() !== null) {
            return;
        }

        $role = new Role([
            'name' => 'Administrator',
            'description' => 'Full access to everything in this company.',
        ]);
        $role->is_admin = true;
        $role->save();
    }
}
