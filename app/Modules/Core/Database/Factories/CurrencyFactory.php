<?php

namespace App\Modules\Core\Database\Factories;

use App\Modules\Core\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Currency> */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->unique()->word().' Dollar',
            'symbol' => '$',
            'exchange_rate' => fake()->randomFloat(4, 0.5, 150),
        ];
    }
}
