<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FaultStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeFaultStatusRequest;
use App\Http\Requests\ListFaultsRequest;
use App\Http\Requests\StoreFaultRequest;
use App\Http\Requests\UpdateFaultRequest;
use App\Http\Resources\FaultResource;
use App\Models\Fault;
use App\Models\Site;
use App\Services\FaultService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FaultController extends Controller
{
    private const RELATIONS = ['site', 'raiser', 'assignee', 'photos'];

    public function __construct(private readonly FaultService $faults) {}

    public function index(ListFaultsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Fault::query()->with(self::RELATIONS);

        if ($user->role === UserRole::Manager) {
            $query->whereHas('site.region', fn ($q) => $q->where('manager_id', $user->id));
        } elseif ($user->role === UserRole::Engineer) {
            $query->whereHas('site.engineers', fn ($q) => $q->where('users.id', $user->id));
        }

        $this->applyFilters($query, $request);

        return FaultResource::collection(
            $query->latest('created_at')->latest('id')
                ->paginate($request->integer('per_page', 15))
                ->withQueryString()
        );
    }

    public function indexForSite(ListFaultsRequest $request, Site $site): AnonymousResourceCollection
    {
        $this->authorize('view', $site);

        $query = $site->faults()->with(self::RELATIONS);

        $this->applyFilters($query, $request);

        return FaultResource::collection(
            $query->latest('created_at')->latest('id')
                ->paginate($request->integer('per_page', 15))
                ->withQueryString()
        );
    }

    public function store(StoreFaultRequest $request, Site $site): JsonResponse
    {
        $fault = $this->faults->create($site, $request->user(), $request->validated());

        return (new FaultResource($fault->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Fault $fault): FaultResource
    {
        $this->authorize('view', $fault);

        return new FaultResource($fault->load(self::RELATIONS));
    }

    public function update(UpdateFaultRequest $request, Fault $fault): FaultResource
    {
        $fault = $this->faults->update($fault, $request->validated());

        return new FaultResource($fault->load(self::RELATIONS));
    }

    public function changeStatus(ChangeFaultStatusRequest $request, Fault $fault): FaultResource
    {
        $fault = $this->faults->changeStatus($fault, FaultStatus::from($request->validated('status')));

        return new FaultResource($fault->load(self::RELATIONS));
    }

    private function applyFilters(Builder $query, ListFaultsRequest $request): void
    {
        $query
            ->when($request->validated('severity'), fn ($q, $severity) => $q->where('severity', $severity))
            ->when($request->validated('category'), fn ($q, $category) => $q->where('category', $category))
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->validated('region_id'), fn ($q, $regionId) => $q->whereHas('site', fn ($q) => $q->where('region_id', $regionId)))
            ->when($request->validated('assigned_to'), fn ($q, $engineerId) => $q->where('assigned_to', $engineerId));

        if ($request->boolean('sla_breached')) {
            $query->where('sla_due_at', '<', now())
                ->whereNotIn('status', [FaultStatus::Resolved->value, FaultStatus::Closed->value]);
        }
    }
}
