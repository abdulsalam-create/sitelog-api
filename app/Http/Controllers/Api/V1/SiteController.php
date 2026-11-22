<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SiteStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListSitesRequest;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Models\User;
use App\Services\SummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class SiteController extends Controller
{
    public function index(ListSitesRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Site::query()->with(['region.manager', 'engineers']);

        if ($user->role === UserRole::Manager) {
            $query->whereIn('region_id', $user->managedRegions()->select('id'));
        } elseif ($user->role === UserRole::Engineer) {
            $query->whereHas('engineers', fn ($q) => $q->where('users.id', $user->id));
        }

        $query
            ->when($request->validated('region_id'), fn ($q, $regionId) => $q->where('region_id', $regionId))
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->validated('power_source'), fn ($q, $source) => $q->where('power_source', $source))
            ->when($request->validated('site_type'), fn ($q, $type) => $q->where('site_type', $type))
            ->when($request->validated('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('site_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('site_code');

        return SiteResource::collection(
            $query->paginate($request->integer('per_page', 15))->withQueryString()
        );
    }

    public function store(StoreSiteRequest $request): JsonResponse
    {
        $site = Site::create($request->validated());

        return (new SiteResource($site->load(['region.manager', 'engineers'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Site $site): SiteResource
    {
        $this->authorize('view', $site);

        return new SiteResource($site->load(['region.manager', 'engineers']));
    }

    public function update(UpdateSiteRequest $request, Site $site): SiteResource
    {
        $site->update($request->validated());

        return new SiteResource($site->load(['region.manager', 'engineers']));
    }

    public function destroy(Site $site): SiteResource
    {
        $this->authorize('decommission', $site);

        $site->update(['status' => SiteStatus::Decommissioned]);

        return new SiteResource($site->load(['region.manager', 'engineers']));
    }

    public function assignEngineer(Site $site, User $user): JsonResponse
    {
        $this->authorize('assignEngineer', $site);

        if ($user->role !== UserRole::Engineer) {
            throw ValidationException::withMessages([
                'user' => ['Only engineers can be assigned to sites.'],
            ]);
        }

        $site->engineers()->syncWithoutDetaching([
            $user->id => ['assigned_at' => now()],
        ]);

        return (new SiteResource($site->load(['region.manager', 'engineers'])))
            ->response()
            ->setStatusCode(201);
    }

    public function removeEngineer(Site $site, User $user): SiteResource
    {
        $this->authorize('removeEngineer', $site);

        $site->engineers()->detach($user->id);

        return new SiteResource($site->load(['region.manager', 'engineers']));
    }

    public function summary(Site $site, SummaryService $summaries): JsonResponse
    {
        $this->authorize('view', $site);

        return response()->json(['data' => $summaries->site($site)]);
    }
}
