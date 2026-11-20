<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'manager' => new UserResource($this->whenLoaded('manager')),
            'sites_count' => $this->whenCounted('sites'),
            'created_at' => $this->created_at,
        ];
    }
}
