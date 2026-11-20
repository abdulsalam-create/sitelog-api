<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitReportResource extends JsonResource
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
            'engineer' => $this->whenLoaded('engineer', fn () => $this->engineer ? new UserResource($this->engineer) : null),
            'visit_date' => $this->visit_date?->format('Y-m-d'),
            'visit_type' => $this->visit_type,
            'grid_power_available' => $this->grid_power_available,
            'generator_hours' => $this->generator_hours,
            'fuel_level_percent' => $this->fuel_level_percent,
            'battery_health' => $this->battery_health,
            'work_summary' => $this->work_summary,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at,
            'review_comment' => $this->review_comment,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? new UserResource($this->reviewer) : null),
            'maintenance_items' => MaintenanceItemResource::collection($this->whenLoaded('maintenanceItems')),
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at,
        ];
    }
}
