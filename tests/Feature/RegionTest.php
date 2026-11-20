<?php

use App\Models\Region;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lets an admin create a region', function () {
    $manager = User::factory()->manager()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/v1/regions', [
        'name' => 'Lagos West',
        'code' => 'lag',
        'manager_id' => $manager->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'LAG')
        ->assertJsonPath('data.manager.id', $manager->id);
});

it('rejects region creation from non admins', function () {
    Sanctum::actingAs(User::factory()->manager()->create());

    $this->postJson('/api/v1/regions', [
        'name' => 'Lagos West',
        'code' => 'LAG',
    ])->assertForbidden();
});

it('validates the region input', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/v1/regions', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'code']);
});

it('only accepts a manager as region manager', function () {
    $engineer = User::factory()->engineer()->create();
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/v1/regions', [
        'name' => 'Lagos West',
        'code' => 'LAG',
        'manager_id' => $engineer->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_id');
});

it('lists regions with pagination metadata', function () {
    Sanctum::actingAs(User::factory()->engineer()->create());
    Region::factory()->count(2)->create();

    $this->getJson('/api/v1/regions')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonCount(2, 'data');
});
