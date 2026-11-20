<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'caption' => $this->caption,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at,
        ];
    }
}
