<?php

namespace App\Modules\Hrm\Database\Seeders;

use App\Modules\Hrm\Models\EntityType;
use Illuminate\Database\Seeder;

/**
 * Default entity types for a NEW company: Department, Division, Branch, Location, Area.
 * Companies rename them or add their own (e.g. Function, Zone).
 * Only runs when the company has no types yet, so it never undoes their changes.
 */
class EntityTypesSeeder extends Seeder
{
    public function run(): void
    {
        if (EntityType::exists()) {
            return;
        }

        foreach (['Department', 'Division', 'Branch', 'Location', 'Area'] as $i => $name) {
            EntityType::create(['name' => $name, 'sequence' => $i + 1]);
        }
    }
}
