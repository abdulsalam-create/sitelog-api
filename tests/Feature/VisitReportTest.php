<?php

use App\Enums\ReportStatus;
use App\Models\Site;
use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('lets an engineer create a draft report with maintenance items', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/reports", reportPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.engineer.id', $engineer->id)
        ->assertJsonCount(1, 'data.maintenance_items');

    $this->assertDatabaseHas('visit_reports', [
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Draft->value,
    ]);
});

it('blocks an engineer from reporting on a site they are not assigned to', function () {
    $engineer = User::factory()->engineer()->create();
    $site = Site::factory()->create();
    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/reports", reportPayload())
        ->assertForbidden();
});

it('blocks managers from creating reports', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/sites/{$site->id}/reports", reportPayload())
        ->assertForbidden();
});

it('rejects a second report for the same site on the same day', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/reports", reportPayload())->assertCreated();
    $this->postJson("/api/v1/sites/{$site->id}/reports", reportPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('visit_date');
});

it('rejects a future visit date', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/reports", reportPayload([
        'visit_date' => now()->addDay()->format('Y-m-d'),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('visit_date');
});

it('rejects fuel levels outside 0 to 100', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/sites/{$site->id}/reports", reportPayload([
        'fuel_level_percent' => 120,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('fuel_level_percent');
});

it('scopes the report list by role', function () {
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();

    $ownSite = siteForEngineer($engineer, $manager);
    $otherSite = Site::factory()->create();

    $ownReport = VisitReport::factory()->create([
        'site_id' => $ownSite->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);
    VisitReport::factory()->create([
        'site_id' => $otherSite->id,
        'visit_date' => now()->subDays(2)->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);
    $this->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownReport->id);

    Sanctum::actingAs($manager);
    $this->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownReport->id);

    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lets the owner edit a draft but nobody else', function () {
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
    $this->putJson("/api/v1/reports/{$report->id}", ['work_summary' => 'Edited'])
        ->assertForbidden();

    Sanctum::actingAs($engineer);
    $this->putJson("/api/v1/reports/{$report->id}", ['work_summary' => 'Edited'])
        ->assertOk()
        ->assertJsonPath('data.work_summary', 'Edited');
});

it('locks submitted reports', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Submitted,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->putJson("/api/v1/reports/{$report->id}", ['work_summary' => 'Nope'])
        ->assertForbidden();
    $this->deleteJson("/api/v1/reports/{$report->id}")
        ->assertForbidden();
});

it('lets a rejected report be edited and resubmitted', function () {
    $manager = User::factory()->manager()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer, $manager);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Rejected,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->putJson("/api/v1/reports/{$report->id}", ['work_summary' => 'Fixed the summary'])
        ->assertOk();

    $this->postJson("/api/v1/reports/{$report->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');
});

it('deletes a draft report', function () {
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->deleteJson("/api/v1/reports/{$report->id}")->assertOk();
    $this->assertDatabaseMissing('visit_reports', ['id' => $report->id]);
});

it('filters reports by status and date range', function () {
    $admin = User::factory()->admin()->create();
    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);

    VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Approved,
        'visit_date' => now()->subDays(3)->format('Y-m-d'),
    ]);
    VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'status' => ReportStatus::Draft,
        'visit_date' => now()->subDays(10)->format('Y-m-d'),
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/reports?status=approved')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/reports?from='.now()->subDays(5)->format('Y-m-d'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'approved');
});

it('loads a page of reports in a constant number of queries', function () {
    $admin = User::factory()->admin()->create();
    $site = Site::factory()->create();
    Sanctum::actingAs($admin);

    foreach (range(1, 5) as $day) {
        VisitReport::factory()->create([
            'site_id' => $site->id,
            'visit_date' => now()->subDays($day)->format('Y-m-d'),
        ]);
    }

    DB::enableQueryLog();
    $this->getJson('/api/v1/reports')->assertOk();
    $smallRun = count(DB::getQueryLog());
    DB::flushQueryLog();

    foreach (range(6, 20) as $day) {
        VisitReport::factory()->create([
            'site_id' => $site->id,
            'visit_date' => now()->subDays($day)->format('Y-m-d'),
        ]);
    }

    DB::flushQueryLog();
    $this->getJson('/api/v1/reports')->assertOk();
    $bigRun = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($bigRun)->toBe($smallRun);
});
