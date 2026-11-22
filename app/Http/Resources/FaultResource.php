<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site' => $this->whenLoaded('site', fn () => [
                'id' => $this->site->id,
                'site_code' => $this->site->site_code,
                'name' => $this->site->name,
            ]),
            'raiser' => $this->whenLoaded('raiser', fn () => $this->raiser ? new UserResource($this->raiser) : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new UserResource($this->assignee) : null),
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'severity' => $this->severity,
            'status' => $this->status,
            'sla_due_at' => $this->sla_due_at,
            'resolved_at' => $this->resolved_at,
            'sla_breached' => $this->isSlaBreached(),
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at,
        ];
    }
}
