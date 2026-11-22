<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()->notifications()->paginate(15);

        return NotificationResource::collection($notifications);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $model = $request->user()->notifications()->findOrFail($notification);

        $model->markAsRead();

        return response()->json(['data' => new NotificationResource($model)]);
    }
}
