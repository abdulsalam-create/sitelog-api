<?php

use App\Enums\SiteStatus;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lets an admin create a site', function () {
    $region = Region::factory()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/v1/sites', [
        'site_code' => 'lag-0001',
        'name' => 'Ikeja Central',
        'region_id' => $region->id,
        'address' => '12 Allen Avenue',
        'latitude' => 6.6018,
        'longitude' => 3.3515,
        'site_type' => 'greenfield',
        'power_source' => 'hybrid',
    ])
        ->assertCreated()
        ->assertJsonPath('data.site_code', 'LAG-0001')
        ->assertJsonPath('data.status', 'active');
});

it('rejects site creation from managers', function () {
    $region = Region::factory()->create();
    Sanctum::actingAs(User::factory()->manager()->create());

    $this->postJson('/api/v1/sites', [
        'site_code' => 'LAG-0001',
        'name' => 'Ikeja Central',
        'region_id' => $region->id,
        'address' => '12 Allen Avenue',
        'latitude' => 6.6018,
        'longitude' => 3.3515,
        'site_type' => 'greenfield',
        'power_source' => 'hybrid',
    ])->assertForbidden();
});

it('shows engineers only the sites they are assigned to', function () {
    $region = Region::factory()->create();
    $assigned = Site::factory()->create(['region_id' => $region->id]);
    Site::factory()->create(['region_id' => $region->id]);

    $engineer = User::factory()->engineer()->create();
    $assigned->engineers()->attach($engineer->id, ['assigned_at' => now()]);

    Sanctum::actingAs($engineer);

    $this->getJson('/api/v1/sites')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assigned->id);
});

it('shows managers only the sites in their regions', function () {
    $manager = User::factory()->manager()->create();
    $ownRegion = Region::factory()->create(['manager_id' => $manager->id]);
    $otherRegion = Region::factory()->create();

    $ownSite = Site::factory()->create(['region_id' => $ownRegion->id]);
    Site::factory()->create(['region_id' => $otherRegion->id]);

    Sanctum::actingAs($manager);

    $this->getJson('/api/v1/sites')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownSite->id);
});

it('blocks engineers from viewing sites they are not assigned to', function () {
    $site = Site::factory()->create();
    Sanctum::actingAs(User::factory()->engineer()->create());

    $this->getJson("/api/v1/sites/{$site->id}")->assertForbidden();
});

it('lets a manager update a site in their region', function () {
    $manager = User::factory()->manager()->create();
    $region = Region::factory()->create(['manager_id' => $manager->id]);
    $site = Site::factory()->create(['region_id' => $region->id]);
    Sanctum::actingAs($manager);

    $this->putJson("/api/v1/sites/{$site->id}", ['name' => 'Renamed Site'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Site');
});

it('blocks a manager from touching another region site', function () {
    $site = Site::factory()->create();
    Sanctum::actingAs(User::factory()->manager()->create());

    $this->putJson("/api/v1/sites/{$site->id}", ['name' => 'Nope'])
        ->assertForbidden();
});

it('blocks a manager from moving a site into another region', function () {
    $manager = User::factory()->manager()->create();
    $ownRegion = Region::factory()->create(['manager_id' => $manager->id]);
    $otherRegion = Region::factory()->create();
    $site = Site::factory()->create(['region_id' => $ownRegion->id]);

    Sanctum::actingAs($manager);

    $this->putJson("/api/v1/sites/{$site->id}", ['region_id' => $otherRegion->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('region_id');
});

it('decommissions a site instead of deleting it', function () {
    $manager = User::factory()->manager()->create();
    $region = Region::factory()->create(['manager_id' => $manager->id]);
    $site = Site::factory()->create(['region_id' => $region->id]);
    Sanctum::actingAs($manager);

    $this->deleteJson("/api/v1/sites/{$site->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'decommissioned');

    $this->assertDatabaseHas('sites', [
        'id' => $site->id,
        'status' => SiteStatus::Decommissioned->value,
    ]);
});

it('assigns and removes engineers', function () {
    $manager = User::factory()->manager()->create();
    $region = Region::factory()->create(['manager_id' => $manager->id]);
    $site = Site::factory()->create(['region_id' => $region->id]);
    $engineer = User::factory()->engineer()->create();
    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/sites/{$site->id}/engineers/{$engineer->id}")
        ->assertCreated();

    expect($site->engineers()->whereKey($engineer->id)->exists())->toBeTrue();

    $this->deleteJson("/api/v1/sites/{$site->id}/engineers/{$engineer->id}")
        ->assertOk();

    expect($site->engineers()->whereKey($engineer->id)->exists())->toBeFalse();
});

it('refuses to assign a non engineer', function () {
    $manager = User::factory()->manager()->create();
    $region = Region::factory()->create(['manager_id' => $manager->id]);
    $site = Site::factory()->create(['region_id' => $region->id]);
    $otherManager = User::factory()->manager()->create();
    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/sites/{$site->id}/engineers/{$otherManager->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('user');
});

it('filters sites by status and search', function () {
    $region = Region::factory()->create();
    Site::factory()->create([
        'region_id' => $region->id,
        'site_code' => 'LAG-0001',
        'status' => SiteStatus::Active,
    ]);
    Site::factory()->create([
        'region_id' => $region->id,
        'site_code' => 'LAG-0002',
        'status' => SiteStatus::Decommissioned,
    ]);

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson('/api/v1/sites?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/sites?search=0002')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.site_code', 'LAG-0002');
});

it('rejects unauthenticated site access', function () {
    $this->getJson('/api/v1/sites')->assertUnauthorized();
});
