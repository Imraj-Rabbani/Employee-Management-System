<?php

namespace App\Modules\Hrm\Database\Factories;

use App\Modules\Hrm\Models\LeaveProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake LeaveProfile rows. Entitlements: $profile->syncEntitlements([$casual->id => 10]).
 *
 * @extends Factory<LeaveProfile>
 */
class LeaveProfileFactory extends Factory
{
    protected $model = LeaveProfile::class;

    public function definition(): array
    {
        return ['name' => 'Leave profile '.fake()->unique()->numberBetween(1, 99999)];
    }
}
