<?php

use App\Enums\FaultStatus;
use App\Enums\ReportStatus;
use App\Models\Fault;
use App\Models\User;
use App\Models\VisitReport;
use App\Notifications\CriticalFaultNotification;
use App\Notifications\LowFuelNotification;
use App\Notifications\ReportReviewedNotification;
use App\Notifications\ReportSubmittedNotification;
use App\Notifications\SlaBreachNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

it('notifies the manager when a report is submitted', function () {
    Notification::fake();

    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/reports/{$report->id}/submit")->assertOk();

    Notification::assertSentTo($manager, ReportSubmittedNotification::class);
});

it('notifies the engineer when a report is reviewed', function () {
    Notification::fake();

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

    $this->postJson("/api/v1/reports/{$report->id}/approve")->assertOk();

    Notification::assertSentTo($engineer, ReportReviewedNotification::class);
});

it('notifies the manager immediately for critical faults only', function () {
    Notification::fake();

    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/faults", [
        'title' => 'Site down',
        'description' => 'Total outage.',
        'category' => 'power',
        'severity' => 'critical',
    ])->assertCreated();

    Notification::assertSentTo($manager, CriticalFaultNotification::class);

    $this->postJson("/api/v1/sites/{$site->id}/faults", [
        'title' => 'Low severity issue',
        'description' => 'Minor issue.',
        'category' => 'equipment',
        'severity' => 'low',
    ])->assertCreated();

    Notification::assertSentToTimes($manager, CriticalFaultNotification::class, 1);
});

it('notifies the manager for low fuel reports', function () {
    Notification::fake();

    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/reports", reportPayload([
        'fuel_level_percent' => 15,
    ]))->assertCreated();

    Notification::assertSentTo($manager, LowFuelNotification::class);
});

it('stays quiet for normal fuel levels', function () {
    Notification::fake();

    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/reports", reportPayload([
        'fuel_level_percent' => 55,
    ]))->assertCreated();

    Notification::assertNotSentTo($manager, LowFuelNotification::class);
});

it('lists and marks notifications read', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/reports/{$report->id}/submit")->assertOk();

    Sanctum::actingAs($manager);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'report_submitted');

    $notification = $manager->notifications()->first();

    $this->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read_at', fn ($readAt) => $readAt !== null);

    expect($manager->notifications()->whereNotNull('read_at')->count())->toBe(1);
});

it('notifies managers about sla breaches exactly once', function () {
    Notification::fake();

    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $fault = Fault::factory()->create([
        'site_id' => $site->id,
        'raised_by' => $engineer->id,
        'status' => FaultStatus::Open,
        'sla_due_at' => now()->subHour(),
    ]);

    $this->artisan('sla:notify-breaches');
    $this->artisan('sla:notify-breaches');

    Notification::assertSentToTimes($manager, SlaBreachNotification::class, 1);

    expect($fault->fresh()->sla_notified_at)->not->toBeNull();
});
