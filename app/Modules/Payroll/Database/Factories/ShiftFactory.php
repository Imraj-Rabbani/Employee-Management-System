<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Payroll\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake Shift rows for tests and demo data: Shift::factory()->create(), Shift::factory()->overnight()->create().
 *
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'code' => 'S-'.fake()->unique()->numerify('####'),
            'name' => 'General',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'hours' => 8,
            'grace_minutes' => 15,
        ];
    }

    /** A night shift that ends the next morning. */
    public function overnight(): static
    {
        return $this->state([
            'name' => 'Night',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'is_overnight' => true,
        ]);
    }
}
