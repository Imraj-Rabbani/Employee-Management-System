<?php

namespace App\Modules\Hrm\Database\Seeders;

use App\Modules\Hrm\Models\OrgUnitType;
use Illuminate\Database\Seeder;

/**
 * Default levels for a NEW company: Company → Division → Department → Section.
 * Companies rename or add their own (e.g. SSS: Function → Department → Location).
 * Only runs when the company has no types yet, so it never undoes their changes.
 */
class OrgUnitTypesSeeder extends Seeder
{
    public function run(): void
    {
        if (OrgUnitType::exists()) {
            return;
        }

        $parent = null;
        foreach (['Company', 'Division', 'Department', 'Section'] as $i => $name) {
            $parent = OrgUnitType::create(['name' => $name, 'parent_type_id' => $parent?->id, 'sequence' => $i + 1]);
        }
    }
}
