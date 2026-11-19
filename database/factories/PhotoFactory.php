<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'photoable_type' => VisitReport::class,
            'photoable_id' => VisitReport::factory(),
            'path' => 'photos/'.fake()->uuid().'.jpg',
            'caption' => fake()->optional(0.7)->sentence(4),
            'uploaded_by' => User::factory(),
        ];
    }
}
