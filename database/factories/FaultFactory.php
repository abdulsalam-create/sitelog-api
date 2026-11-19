<?php

namespace Database\Factories;

use App\Enums\FaultCategory;
use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Models\Fault;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FaultFactory extends Factory
{
    public function definition(): array
    {
        $severity = fake()->randomElement(FaultSeverity::cases());

        return [
            'site_id' => Site::factory(),
            'raised_by' => User::factory(),
            'assigned_to' => null,
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(FaultCategory::cases()),
            'severity' => $severity,
            'status' => FaultStatus::Open,
            'sla_due_at' => now()->addHours($severity->slaHours()),
            'resolved_at' => null,
        ];
    }
}
