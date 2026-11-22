<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegionRequest;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Services\SummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $regions = Region::with('manager')
            ->withCount('sites')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return RegionResource::collection($regions);
    }

    public function store(StoreRegionRequest $request): JsonResponse
    {
        $region = Region::create($request->validated());

        return (new RegionResource($region->load('manager')))
            ->response()
            ->setStatusCode(201);
    }

    public function summary(Region $region, SummaryService $summaries): JsonResponse
    {
        $this->authorize('summary', $region);

        return response()->json(['data' => $summaries->region($region)]);
    }
}
