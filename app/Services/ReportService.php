<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Events\LowFuelRecorded;
use App\Events\ReportReviewed;
use App\Events\ReportSubmitted;
use App\Models\Site;
use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function create(Site $site, User $engineer, array $data): VisitReport
    {
        return DB::transaction(function () use ($site, $engineer, $data) {
            $report = $site->visitReports()->create([
                ...Arr::except($data, ['maintenance_items']),
                'engineer_id' => $engineer->id,
                'status' => ReportStatus::Draft,
            ]);

            $report->maintenanceItems()->createMany($data['maintenance_items'] ?? []);

            if ($report->fuel_level_percent < 20) {
                LowFuelRecorded::dispatch($report);
            }

            return $report->refresh();
        });
    }

    public function update(VisitReport $report, array $data): VisitReport
    {
        return DB::transaction(function () use ($report, $data) {
            $report->update(Arr::except($data, ['maintenance_items']));

            if (array_key_exists('maintenance_items', $data)) {
                $report->maintenanceItems()->delete();
                $report->maintenanceItems()->createMany($data['maintenance_items'] ?? []);
            }

            return $report->refresh();
        });
    }

    public function delete(VisitReport $report): void
    {
        DB::transaction(function () use ($report) {
            $report->photos()->delete();
            $report->delete();
        });
    }

    public function submit(VisitReport $report): VisitReport
    {
        $report->update(['status' => ReportStatus::Submitted]);

        ReportSubmitted::dispatch($report);

        return $report;
    }

    public function approve(VisitReport $report, User $reviewer, ?string $comment): VisitReport
    {
        $report->update([
            'status' => ReportStatus::Approved,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ]);

        ReportReviewed::dispatch($report);

        return $report;
    }

    public function reject(VisitReport $report, User $reviewer, string $comment): VisitReport
    {
        $report->update([
            'status' => ReportStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ]);

        ReportReviewed::dispatch($report);

        return $report;
    }
}
