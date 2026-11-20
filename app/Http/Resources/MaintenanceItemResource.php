<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_name' => $this->item_name,
            'action' => $this->action,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
        ];
    }
}
