<?php

namespace App\Modules\Payroll\Database\Factories;

use App\Modules\Payroll\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fake devices: Device::factory()->create(['ip' => '192.168.1.201']).
 *
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'name' => 'Gate '.fake()->unique()->numberBetween(1, 99999),
            'ip' => fake()->localIpv4(),
        ];
    }
}
