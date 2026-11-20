<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_code' => $this->site_code,
            'name' => $this->name,
            'region' => new RegionResource($this->whenLoaded('region')),
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'site_type' => $this->site_type,
            'power_source' => $this->power_source,
            'status' => $this->status,
            'engineers' => UserResource::collection($this->whenLoaded('engineers')),
            'created_at' => $this->created_at,
        ];
    }
}
