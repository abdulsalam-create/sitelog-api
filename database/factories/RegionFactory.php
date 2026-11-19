<?php

namespace Database\Factories;

use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Region',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'manager_id' => User::factory()->manager(),
        ];
    }
}
