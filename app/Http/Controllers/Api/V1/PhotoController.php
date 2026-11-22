<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePhotosRequest;
use App\Http\Resources\PhotoResource;
use App\Models\Fault;
use App\Models\Photo;
use App\Models\VisitReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PhotoController extends Controller
{
    public function store(StorePhotosRequest $request, string $type, int $id): JsonResponse
    {
        $parent = $this->resolveParent($type, $id);

        $this->authorize('create', [Photo::class, $parent]);

        $files = $request->file('photos');

        if ($parent->photos()->count() + count($files) > 10) {
            throw ValidationException::withMessages([
                'photos' => ['A report or fault can hold at most 10 photos.'],
            ]);
        }

        $disk = Storage::disk(config('filesystems.default'));

        $photos = DB::transaction(function () use ($parent, $files, $request) {
            return collect($files)->map(function ($file) use ($parent, $request) {
                $path = $file->store('photos', ['disk' => config('filesystems.default')]);

                return $parent->photos()->create([
                    'path' => $path,
                    'caption' => $request->input('caption'),
                    'uploaded_by' => $request->user()->id,
                ]);
            });
        });

        return PhotoResource::collection($photos)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Photo $photo): JsonResponse
    {
        $this->authorize('delete', $photo);

        Storage::disk(config('filesystems.default'))->delete($photo->path);

        $photo->delete();

        return response()->json(['message' => 'Photo deleted.']);
    }

    private function resolveParent(string $type, int $id): VisitReport|Fault
    {
        return match ($type) {
            'reports' => VisitReport::findOrFail($id),
            'faults' => Fault::findOrFail($id),
        };
    }
}
