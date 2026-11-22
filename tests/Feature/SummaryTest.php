<?php

use App\Enums\FaultCategory;
use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Enums\SiteStatus;
use App\Models\Fault;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use App\Models\VisitReport;
use Laravel\Sanctum\Sanctum;

it('computes the site summary numbers', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);

    foreach ([2, 3, 4] as $daysAgo) {
        VisitReport::factory()->create([
            'site_id' => $site->id,
            'engineer_id' => $engineer->id,
            'visit_date' => now()->subDays($daysAgo)->format('Y-m-d'),
            'fuel_level_percent' => 50,
            'grid_power_available' => true,
        ]);
    }

    VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDays(40)->format('Y-m-d'),
        'fuel_level_percent' => 10,
        'grid_power_available' => false,
    ]);

    VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
        'fuel_level_percent' => 60,
        'grid_power_available' => false,
    ]);

    Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'severity' => FaultSeverity::Critical,
        'status' => FaultStatus::Open,
        'sla_due_at' => now()->addHours(1),
    ]);

    Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'severity' => FaultSeverity::High,
        'status' => FaultStatus::Open,
        'sla_due_at' => now()->subHour(),
    ]);

    Sanctum::actingAs($manager);

    $response = $this->getJson("/api/v1/sites/{$site->id}/summary")
        ->assertOk();

    expect($response->json('data'))->toMatchArray([
        'total_visits' => 5,
        'last_visit_date' => now()->subDay()->format('Y-m-d'),
        'average_fuel_level_last_7_days' => 52.5,
        'open_faults_by_severity' => ['critical' => 1, 'high' => 1],
        'sla_breached_faults' => 1,
        'grid_outages_last_30_days' => 1,
    ]);
});

it('blocks engineers from summaries of unassigned sites', function () {
    $site = Site::factory()->create();
    Sanctum::actingAs(User::factory()->engineer()->create());

    $this->getJson("/api/v1/sites/{$site->id}/summary")->assertForbidden();
});

it('computes the region summary numbers', function () {
    $manager = User::factory()->manager()->create();
    $region = Region::factory()->create(['manager_id' => $manager->id]);
    $siteA = Site::factory()->create(['region_id' => $region->id]);
    $siteB = Site::factory()->create(['region_id' => $region->id, 'status' => SiteStatus::Decommissioned]);

    Fault::factory()->count(2)->create([
        'site_id' => $siteA->id,
        'raised_by' => User::factory(),
        'category' => FaultCategory::Power,
        'status' => FaultStatus::Open,
        'sla_due_at' => now()->addDay(),
    ]);

    Fault::factory()->create([
        'site_id' => $siteA->id,
        'raised_by' => User::factory(),
        'category' => FaultCategory::Civil,
        'status' => FaultStatus::Resolved,
        'resolved_at' => now(),
        'sla_due_at' => now()->addDay(),
    ]);

    Fault::factory()->create([
        'site_id' => $siteB->id,
        'raised_by' => User::factory(),
        'category' => FaultCategory::Security,
        'status' => FaultStatus::Resolved,
        'resolved_at' => now(),
        'sla_due_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($manager);

    $response = $this->getJson("/api/v1/regions/{$region->id}/summary")->assertOk();

    expect($response->json('data'))->toMatchArray([
        'sites_by_status' => ['active' => 1, 'decommissioned' => 1],
        'open_faults_by_category' => ['power' => 2],
        'sla_compliance_rate' => 50.0,
    ]);

    expect($response->json('data.top_5_sites_by_faults'))->toHaveCount(2);
    expect($response->json('data.top_5_sites_by_faults.0.site.id'))->toBe($siteA->id);
    expect($response->json('data.top_5_sites_by_faults.0.faults'))->toBe(3);
});

it('keeps region summaries to admins and the region manager', function () {
    $manager = User::factory()->manager()->create();
    $region = Region::factory()->create(['manager_id' => $manager->id]);

    Sanctum::actingAs(User::factory()->manager()->create());
    $this->getJson("/api/v1/regions/{$region->id}/summary")->assertForbidden();

    Sanctum::actingAs($manager);
    $this->getJson("/api/v1/regions/{$region->id}/summary")->assertOk();

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->getJson("/api/v1/regions/{$region->id}/summary")->assertOk();
});

it('returns a null compliance rate when nothing is resolved', function () {
    $manager = User::factory()->manager()->create();
    $region = Region::factory()->create(['manager_id' => $manager->id]);
    $site = Site::factory()->create(['region_id' => $region->id]);

    Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => User::factory(),
        'status' => FaultStatus::Open,
        'sla_due_at' => now()->addDay(),
    ]);

    Sanctum::actingAs($manager);

    $this->getJson("/api/v1/regions/{$region->id}/summary")
        ->assertOk()
        ->assertJsonPath('data.sla_compliance_rate', null);
});
