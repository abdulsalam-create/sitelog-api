<?php

use App\Enums\ReportStatus;
use App\Models\User;
use App\Models\VisitReport;
use Laravel\Sanctum\Sanctum;

it('submits a draft report', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/reports/{$report->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');
});

it('blocks an engineer from submitting someone else report', function () {
    $engineer = User::factory()->engineer()->create();
    $other = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $site->engineers()->attach($other->id, ['assigned_at' => now()]);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($other);

    $this->postJson("/api/v1/reports/{$report->id}/submit")
        ->assertForbidden();
});

it('lets the region manager approve a submitted report', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Submitted,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/reports/{$report->id}/approve", ['review_comment' => 'Looks good'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.reviewer.id', $manager->id);

    $this->assertDatabaseHas('visit_reports', [
        'id' => $report->id,
        'status' => ReportStatus::Approved->value,
        'reviewed_by' => $manager->id,
    ]);
});

it('lets an admin approve a submitted report', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Submitted,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/v1/reports/{$report->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('blocks a manager from another region from approving', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Submitted,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs(User::factory()->manager()->create());

    $this->postJson("/api/v1/reports/{$report->id}/approve")
        ->assertForbidden();
});

it('blocks engineers from approving', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Submitted,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/reports/{$report->id}/approve")
        ->assertForbidden();
});

it('blocks approving a report that is still a draft', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/reports/{$report->id}/approve")
        ->assertForbidden();
});

it('requires a comment to reject and records it', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Submitted,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/reports/{$report->id}/reject", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('review_comment');

    $this->postJson("/api/v1/reports/{$report->id}/reject", [
        'review_comment' => 'Fuel reading does not match the photo.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_comment', 'Fuel reading does not match the photo.');
});
