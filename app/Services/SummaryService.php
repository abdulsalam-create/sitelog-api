<?php

namespace App\Services;

use App\Enums\FaultStatus;
use App\Models\Fault;
use App\Models\Region;
use App\Models\Site;
use Illuminate\Support\Carbon;

class SummaryService
{
    public function site(Site $site): array
    {
        $now = now();
        $openStatuses = [FaultStatus::Open->value, FaultStatus::InProgress->value];
        $lastVisitDate = $site->visitReports()->max('visit_date');

        return [
            'total_visits' => $site->visitReports()->count(),
            'last_visit_date' => $lastVisitDate
                ? Carbon::parse($lastVisitDate)->format('Y-m-d')
                : null,
            'average_fuel_level_last_7_days' => round(
                (float) ($site->visitReports()
                    ->where('visit_date', '>=', $now->copy()->subDays(7))
                    ->avg('fuel_level_percent') ?? 0),
                2
            ),
            'open_faults_by_severity' => $site->faults()
                ->whereIn('status', $openStatuses)
                ->selectRaw('severity, count(*) as total')
                ->groupBy('severity')
                ->pluck('total', 'severity'),
            'sla_breached_faults' => $site->faults()
                ->whereIn('status', $openStatuses)
                ->where('sla_due_at', '<', $now)
                ->count(),
            'grid_outages_last_30_days' => $site->visitReports()
                ->where('visit_date', '>=', $now->copy()->subDays(30))
                ->where('grid_power_available', false)
                ->count(),
        ];
    }

    public function region(Region $region): array
    {
        $now = now();
        $siteIds = $region->sites()->select('id');

        $resolvedWithinSla = Fault::whereIn('site_id', $siteIds)
            ->where('status', FaultStatus::Resolved)
            ->whereColumn('resolved_at', '<=', 'sla_due_at')
            ->count();

        $resolvedTotal = Fault::whereIn('site_id', $siteIds)
            ->where('status', FaultStatus::Resolved)
            ->count();

        return [
            'sites_by_status' => $region->sites()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'open_faults_by_category' => Fault::whereIn('site_id', $siteIds)
                ->whereIn('status', [FaultStatus::Open->value, FaultStatus::InProgress->value])
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->pluck('total', 'category'),
            'sla_compliance_rate' => $resolvedTotal > 0
                ? round(($resolvedWithinSla / $resolvedTotal) * 100, 2)
                : null,
            'top_5_sites_by_faults' => Fault::whereIn('site_id', $siteIds)
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->selectRaw('site_id, count(*) as total')
                ->groupBy('site_id')
                ->orderByDesc('total')
                ->limit(5)
                ->with('site')
                ->get()
                ->map(fn (Fault $row) => [
                    'site' => [
                        'id' => $row->site->id,
                        'site_code' => $row->site->site_code,
                        'name' => $row->site->name,
                    ],
                    'faults' => $row->total,
                ]),
        ];
    }
}
