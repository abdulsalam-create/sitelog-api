<?php

use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function siteForEngineer(User $engineer, ?User $manager = null): Site
{
    $region = Region::factory()->create($manager ? ['manager_id' => $manager->id] : []);
    $site = Site::factory()->create(['region_id' => $region->id]);
    $site->engineers()->attach($engineer->id, ['assigned_at' => now()]);

    return $site;
}

function reportPayload(array $overrides = []): array
{
    return array_merge([
        'visit_date' => now()->subDay()->format('Y-m-d'),
        'visit_type' => 'routine',
        'grid_power_available' => true,
        'generator_hours' => 4.5,
        'fuel_level_percent' => 65,
        'battery_health' => 'good',
        'work_summary' => 'Routine check completed.',
        'maintenance_items' => [
            ['item_name' => 'diesel', 'action' => 'refilled', 'quantity' => 20, 'unit' => 'litres'],
        ],
    ], $overrides);
}
