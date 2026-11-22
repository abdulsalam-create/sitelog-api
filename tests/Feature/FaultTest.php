<?php

use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Models\Fault;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('lets an assigned engineer raise a fault', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/faults", [
        'title' => 'Generator down',
        'description' => 'Generator failed to start during checks.',
        'category' => 'power',
        'severity' => 'high',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.severity', 'high');
});

it('blocks an engineer from raising a fault on an unassigned site', function () {
    $engineer = User::factory()->engineer()->create();
    $site = Site::factory()->create();
    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/faults", [
        'title' => 'Generator down',
        'description' => 'Generator failed to start.',
        'category' => 'power',
        'severity' => 'high',
    ])->assertForbidden();
});

it('lets a regional manager raise a fault on their own site', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/sites/{$site->id}/faults", [
        'title' => 'Fence damage',
        'description' => 'Perimeter fence damaged.',
        'category' => 'security',
        'severity' => 'low',
    ])->assertCreated();
});

it('sets the sla due time from severity', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    Sanctum::actingAs($engineer);

    $expectations = [
        'critical' => 4,
        'high' => 24,
        'medium' => 72,
        'low' => 168,
    ];

    foreach ($expectations as $severity => $hours) {
        $response = $this->postJson("/api/v1/sites/{$site->id}/faults", [
            'title' => "Fault {$severity}",
            'description' => 'Fault description.',
            'category' => 'equipment',
            'severity' => $severity,
        ]);

        $fault = Fault::find($response->json('data.id'));

        expect((int) $fault->created_at->diffInHours($fault->sla_due_at))->toBe($hours);
    }
});

it('lets a manager assign a fault to a site engineer', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $fault = Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'status' => FaultStatus::Open,
    ]);

    Sanctum::actingAs($manager);

    $this->putJson("/api/v1/faults/{$fault->id}", ['assigned_to' => $engineer->id])
        ->assertOk()
        ->assertJsonPath('data.assignee.id', $engineer->id);
});

it('rejects assigning an outsider engineer', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $outsider = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $fault = Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'status' => FaultStatus::Open,
    ]);

    Sanctum::actingAs($manager);

    $this->putJson("/api/v1/faults/{$fault->id}", ['assigned_to' => $outsider->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('assigned_to');
});

it('rejects assigning a non engineer', function () {
    $manager = User::factory()->manager()->create();
    $otherManager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $site->engineers()->attach($otherManager->id, ['assigned_at' => now()]);
    $fault = Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'status' => FaultStatus::Open,
    ]);

    Sanctum::actingAs($manager);

    $this->putJson("/api/v1/faults/{$fault->id}", ['assigned_to' => $otherManager->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('assigned_to');
});

it('walks the status machine and stamps resolved_at', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $fault = Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'assigned_to' => $engineer->id,
        'status' => FaultStatus::Open,
        'sla_due_at' => now()->addHours(24),
    ]);

    Sanctum::actingAs($engineer);

    $this->patchJson("/api/v1/faults/{$fault->id}/status", ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->patchJson("/api/v1/faults/{$fault->id}/status", ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    expect($fault->fresh()->resolved_at)->not->toBeNull();
});

it('rejects skipping steps in the status machine', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $fault = Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'assigned_to' => $engineer->id,
        'status' => FaultStatus::Open,
    ]);

    Sanctum::actingAs($manager);

    $this->patchJson("/api/v1/faults/{$fault->id}/status", ['status' => 'resolved'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('only lets managers close and reopen faults', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $fault = Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'assigned_to' => $engineer->id,
        'status' => FaultStatus::Resolved,
        'resolved_at' => now(),
    ]);

    Sanctum::actingAs($engineer);
    $this->patchJson("/api/v1/faults/{$fault->id}/status", ['status' => 'closed'])
        ->assertForbidden();

    Sanctum::actingAs($manager);
    $this->patchJson("/api/v1/faults/{$fault->id}/status", ['status' => 'closed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    $this->patchJson("/api/v1/faults/{$fault->id}/status", ['status' => 'open'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('reopens a resolved fault and resets its sla clock', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $fault = Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'assigned_to' => $engineer->id,
        'status' => FaultStatus::Resolved,
        'resolved_at' => now()->subDay(),
        'sla_due_at' => now()->subDay(),
    ]);

    Sanctum::actingAs($manager);

    $this->patchJson("/api/v1/faults/{$fault->id}/status", ['status' => 'open'])
        ->assertOk()
        ->assertJsonPath('data.status', 'open');

    $fault->refresh();

    expect($fault->resolved_at)->toBeNull();
    expect($fault->sla_due_at->isFuture())->toBeTrue();
});

it('filters faults and flags sla breaches', function () {
    $admin = User::factory()->admin()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);

    Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'severity' => FaultSeverity::High,
        'status' => FaultStatus::Open,
        'sla_due_at' => now()->addHours(10),
    ]);

    $breached = Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'severity' => FaultSeverity::Critical,
        'status' => FaultStatus::Open,
        'sla_due_at' => now()->subHour(),
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/faults?severity=high')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/faults?sla_breached=true')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $breached->id)
        ->assertJsonPath('data.0.sla_breached', true);
});

it('scopes fault lists by role', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $otherSite = Site::factory()->create(['region_id' => Region::factory()->create()->id]);

    $own = Fault::factory()->create(['site_id' => $site->id, 'raised_by' => $engineer->id]);
    Fault::factory()->create(['site_id' => $otherSite->id]);

    Sanctum::actingAs($engineer);
    $this->getJson('/api/v1/faults')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);

    Sanctum::actingAs($manager);
    $this->getJson('/api/v1/faults')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);

    Sanctum::actingAs(User::factory()->admin()->create());
    $this->getJson('/api/v1/faults')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
