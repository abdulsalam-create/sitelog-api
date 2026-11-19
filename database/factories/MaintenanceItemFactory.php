<?php

namespace Database\Factories;

use App\Enums\MaintenanceAction;
use App\Enums\MaintenanceUnit;
use App\Models\VisitReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenanceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_report_id' => VisitReport::factory(),
            'item_name' => fake()->randomElement(['diesel', 'air filter', 'battery', 'oil filter', 'coolant', 'cable']),
            'action' => fake()->randomElement(MaintenanceAction::cases()),
            'quantity' => fake()->randomFloat(2, 1, 40),
            'unit' => fake()->randomElement(MaintenanceUnit::cases()),
        ];
    }
}
