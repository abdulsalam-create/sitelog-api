<?php

namespace Database\Factories;

use App\Enums\BatteryHealth;
use App\Enums\ReportStatus;
use App\Enums\VisitType;
use App\Models\Site;
use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'engineer_id' => User::factory()->engineer(),
            'visit_date' => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'visit_type' => fake()->randomElement(VisitType::cases()),
            'grid_power_available' => fake()->boolean(70),
            'generator_hours' => fake()->randomFloat(2, 0, 12),
            'fuel_level_percent' => fake()->numberBetween(0, 100),
            'battery_health' => fake()->randomElement(BatteryHealth::cases()),
            'work_summary' => fake()->sentence(18),
            'status' => ReportStatus::Draft,
        ];
    }
}
