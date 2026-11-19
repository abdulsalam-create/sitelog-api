<?php

namespace Database\Factories;

use App\Enums\PowerSource;
use App\Enums\SiteStatus;
use App\Enums\SiteType;
use App\Models\Region;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_code' => strtoupper(fake()->unique()->bothify('???-####')),
            'name' => fake()->city().' Site',
            'region_id' => Region::factory(),
            'address' => fake()->streetAddress(),
            'latitude' => fake()->latitude(4.0, 13.5),
            'longitude' => fake()->longitude(3.0, 14.5),
            'site_type' => fake()->randomElement(SiteType::cases()),
            'power_source' => fake()->randomElement(PowerSource::cases()),
            'status' => SiteStatus::Active,
        ];
    }
}
