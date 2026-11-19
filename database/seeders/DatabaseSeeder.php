<?php

namespace Database\Seeders;

use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Enums\ReportStatus;
use App\Models\Fault;
use App\Models\MaintenanceItem;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'SiteLog Admin',
            'email' => 'admin@sitelog.test',
            'staff_id' => 'MTN-00001',
        ]);

        $managers = collect(range(1, 3))->map(fn (int $i) => User::factory()->manager()->create([
            'email' => "manager{$i}@sitelog.test",
        ]));

        $engineers = collect(range(1, 8))->map(fn (int $i) => User::factory()->engineer()->create([
            'email' => "engineer{$i}@sitelog.test",
        ]));

        $regionData = [
            ['name' => 'Lagos West', 'code' => 'LAG', 'sites' => 9],
            ['name' => 'Abuja Central', 'code' => 'ABJ', 'sites' => 8],
            ['name' => 'Port Harcourt', 'code' => 'PHC', 'sites' => 8],
        ];

        $sites = collect();

        foreach ($regionData as $index => $data) {
            $region = Region::factory()->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'manager_id' => $managers[$index]->id,
            ]);

            for ($i = 1; $i <= $data['sites']; $i++) {
                $sites->push(Site::factory()->create([
                    'region_id' => $region->id,
                    'site_code' => $data['code'].'-'.str_pad($i, 4, '0', STR_PAD_LEFT),
                ]));
            }
        }

        foreach ($sites as $site) {
            $assigned = $engineers->random(rand(2, 4));
            $site->engineers()->attach(
                $assigned->pluck('id'),
                ['assigned_at' => now()->subDays(rand(5, 90))]
            );
        }

        $this->createReports($sites, $admin);
        $this->createFaults($sites);
    }

    private function createReports($sites, User $admin): void
    {
        $statuses = array_merge(
            array_fill(0, 10, ReportStatus::Draft),
            array_fill(0, 15, ReportStatus::Submitted),
            array_fill(0, 25, ReportStatus::Approved),
            array_fill(0, 10, ReportStatus::Rejected),
        );
        shuffle($statuses);

        $usedSlots = [];
        $created = 0;

        while ($created < 60) {
            $site = $sites->random();
            $engineer = $site->engineers->random();
            $visitDate = now()->subDays(rand(1, 45))->format('Y-m-d');
            $slot = "{$site->id}-{$engineer->id}-{$visitDate}";

            if (isset($usedSlots[$slot])) {
                continue;
            }

            $usedSlots[$slot] = true;
            $status = $statuses[$created];
            $reviewed = in_array($status, [ReportStatus::Approved, ReportStatus::Rejected], true);
            $reviewerId = $site->region->manager_id ?? $admin->id;

            $report = VisitReport::factory()->create([
                'site_id' => $site->id,
                'engineer_id' => $engineer->id,
                'visit_date' => $visitDate,
                'status' => $status,
                'fuel_level_percent' => $created % 7 === 0 ? rand(5, 19) : rand(20, 100),
                'reviewed_by' => $reviewed ? $reviewerId : null,
                'reviewed_at' => $reviewed ? now()->subDays(rand(0, 10)) : null,
                'review_comment' => $status === ReportStatus::Rejected ? fake()->sentence(8) : null,
            ]);

            if (fake()->boolean(75)) {
                MaintenanceItem::factory()
                    ->count(rand(1, 3))
                    ->create(['visit_report_id' => $report->id]);
            }

            $created++;
        }
    }

    private function createFaults($sites): void
    {
        $severities = array_merge(
            array_fill(0, 6, FaultSeverity::Critical),
            array_fill(0, 8, FaultSeverity::High),
            array_fill(0, 9, FaultSeverity::Medium),
            array_fill(0, 7, FaultSeverity::Low),
        );
        shuffle($severities);

        $statuses = array_merge(
            array_fill(0, 10, FaultStatus::Open),
            array_fill(0, 8, FaultStatus::InProgress),
            array_fill(0, 7, FaultStatus::Resolved),
            array_fill(0, 5, FaultStatus::Closed),
        );
        shuffle($statuses);

        for ($i = 0; $i < 30; $i++) {
            $site = $sites->random();
            $raisedBy = $site->engineers->random();
            $severity = $severities[$i];
            $status = $statuses[$i];
            $createdAt = now()->subDays(rand(0, 30))->subHours(rand(0, 23));
            $slaDueAt = $createdAt->copy()->addHours($severity->slaHours());

            $resolvedAt = null;

            if (in_array($status, [FaultStatus::Resolved, FaultStatus::Closed], true)) {
                $resolvedAt = $slaDueAt->copy()->addHours(rand(-10, 30));

                if ($resolvedAt->isFuture()) {
                    $resolvedAt = now()->subHours(rand(1, 12));
                }
            }

            Fault::factory()->create([
                'site_id' => $site->id,
                'raised_by' => $raisedBy->id,
                'assigned_to' => fake()->boolean(70) ? $site->engineers->random()->id : null,
                'severity' => $severity,
                'status' => $status,
                'sla_due_at' => $slaDueAt,
                'resolved_at' => $resolvedAt,
                'created_at' => $createdAt,
                'updated_at' => $resolvedAt ?? now(),
            ]);
        }
    }
}
