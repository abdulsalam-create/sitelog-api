<?php

use App\Models\Fault;
use App\Models\Photo;
use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('uploads photos to a report', function () {
    Storage::fake('public');

    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/reports/{$report->id}/photos", [
        'photos' => [
            UploadedFile::fake()->image('meter.jpg'),
            UploadedFile::fake()->image('panel.png'),
        ],
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'data');

    expect($report->photos()->count())->toBe(2);
    Storage::disk('public')->assertExists($report->photos->first()->path);
});

it('uploads photos to a fault', function () {
    Storage::fake('public');

    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $fault = Fault::factory()->create(['site_id' => $site->id, 'raised_by' => $engineer->id]);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/faults/{$fault->id}/photos", [
        'photos' => [UploadedFile::fake()->image('damage.webp')],
    ])->assertCreated();

    expect($fault->photos()->count())->toBe(1);
});

it('rejects non images', function () {
    Storage::fake('public');

    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/reports/{$report->id}/photos", [
        'photos' => [UploadedFile::fake()->create('doc.pdf', 100)],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photos.0');
});

it('rejects files over 5 MB', function () {
    Storage::fake('public');

    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/reports/{$report->id}/photos", [
        'photos' => [UploadedFile::fake()->image('big.jpg')->size(6144)],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photos.0');
});

it('caps photos at ten per parent', function () {
    Storage::fake('public');

    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    Photo::factory()->count(8)->create([
        'photoable_type' => VisitReport::class,
        'photoable_id' => $report->id,
        'uploaded_by' => $engineer->id,
    ]);

    $this->postJson("/api/v1/reports/{$report->id}/photos", [
        'photos' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('photos');

    expect($report->photos()->count())->toBe(8);
});

it('blocks photo uploads for outsiders', function () {
    Storage::fake('public');

    $engineer = User::factory()->engineer()->create();
    $outsider = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($outsider);

    $this->postJson("/api/v1/reports/{$report->id}/photos", [
        'photos' => [UploadedFile::fake()->image('a.jpg')],
    ])->assertForbidden();
});

it('removes the file from disk when a photo is deleted', function () {
    Storage::fake('public');

    $engineer = User::factory()->engineer()->create();
    $site = siteForEngineer($engineer);
    $report = VisitReport::factory()->create([
        'site_id' => $site->id,
        'engineer_id' => $engineer->id,
        'visit_date' => now()->subDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($engineer);

    $this->postJson("/api/v1/reports/{$report->id}/photos", [
        'photos' => [UploadedFile::fake()->image('a.jpg')],
    ])->assertCreated();

    $photo = $report->photos->first();
    Storage::disk('public')->assertExists($photo->path);

    $this->deleteJson("/api/v1/photos/{$photo->id}")->assertOk();

    expect(Photo::find($photo->id))->toBeNull();
    Storage::disk('public')->assertMissing($photo->path);
});

it('returns 404 for an unknown parent type', function () {
    $engineer = User::factory()->engineer()->create();
    Sanctum::actingAs($engineer);

    $this->postJson('/api/v1/messages/1/photos', [
        'photos' => [UploadedFile::fake()->image('a.jpg')],
    ])->assertNotFound();
});
