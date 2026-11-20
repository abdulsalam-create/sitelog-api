<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveReportRequest;
use App\Http\Requests\ListReportsRequest;
use App\Http\Requests\RejectReportRequest;
use App\Http\Requests\StoreVisitReportRequest;
use App\Http\Requests\UpdateVisitReportRequest;
use App\Http\Resources\VisitReportResource;
use App\Models\Site;
use App\Models\VisitReport;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VisitReportController extends Controller
{
    private const RELATIONS = ['site', 'engineer', 'reviewer', 'maintenanceItems', 'photos'];

    public function __construct(private readonly ReportService $reports) {}

    public function index(ListReportsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = VisitReport::query()->with(self::RELATIONS);

        if ($user->role === UserRole::Manager) {
            $query->whereHas('site.region', fn ($q) => $q->where('manager_id', $user->id));
        } elseif ($user->role === UserRole::Engineer) {
            $query->whereHas('site.engineers', fn ($q) => $q->where('users.id', $user->id));
        }

        $this->applyFilters($query, $request);

        return VisitReportResource::collection(
            $query->latest('visit_date')->latest('id')
                ->paginate($request->integer('per_page', 15))
                ->withQueryString()
        );
    }

    public function indexForSite(ListReportsRequest $request, Site $site): AnonymousResourceCollection
    {
        $this->authorize('viewReports', $site);

        $query = $site->visitReports()->with(self::RELATIONS);

        $this->applyFilters($query, $request);

        return VisitReportResource::collection(
            $query->latest('visit_date')->latest('id')
                ->paginate($request->integer('per_page', 15))
                ->withQueryString()
        );
    }

    public function store(StoreVisitReportRequest $request, Site $site): JsonResponse
    {
        $report = $this->reports->create($site, $request->user(), $request->validated());

        return (new VisitReportResource($report->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(VisitReport $report): VisitReportResource
    {
        $this->authorize('view', $report);

        return new VisitReportResource($report->load(self::RELATIONS));
    }

    public function update(UpdateVisitReportRequest $request, VisitReport $report): VisitReportResource
    {
        $report = $this->reports->update($report, $request->validated());

        return new VisitReportResource($report->load(self::RELATIONS));
    }

    public function destroy(VisitReport $report): JsonResponse
    {
        $this->authorize('delete', $report);

        $this->reports->delete($report);

        return response()->json(['message' => 'Report deleted.']);
    }

    public function submit(VisitReport $report): VisitReportResource
    {
        $this->authorize('submit', $report);

        $report = $this->reports->submit($report);

        return new VisitReportResource($report->load(self::RELATIONS));
    }

    public function approve(ApproveReportRequest $request, VisitReport $report): VisitReportResource
    {
        $report = $this->reports->approve($report, $request->user(), $request->validated('review_comment'));

        return new VisitReportResource($report->load(self::RELATIONS));
    }

    public function reject(RejectReportRequest $request, VisitReport $report): VisitReportResource
    {
        $report = $this->reports->reject($report, $request->user(), $request->validated('review_comment'));

        return new VisitReportResource($report->load(self::RELATIONS));
    }

    private function applyFilters(Builder $query, ListReportsRequest $request): void
    {
        $query
            ->when($request->validated('site_id'), fn ($q, $siteId) => $q->where('site_id', $siteId))
            ->when($request->validated('region_id'), fn ($q, $regionId) => $q->whereHas('site', fn ($q) => $q->where('region_id', $regionId)))
            ->when($request->validated('engineer_id'), fn ($q, $engineerId) => $q->where('engineer_id', $engineerId))
            ->when($request->validated('visit_type'), fn ($q, $type) => $q->where('visit_type', $type))
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->validated('from'), fn ($q, $from) => $q->whereDate('visit_date', '>=', $from))
            ->when($request->validated('to'), fn ($q, $to) => $q->whereDate('visit_date', '<=', $to));
    }
}
