<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverTripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'departs_at' => $this->departs_at->toIso8601String(),
            'status' => $this->status,
            'route' => new RouteResource($this->whenLoaded('route')),
            'bus' => new BusResource($this->whenLoaded('bus')),
            'seats_taken' => (int) ($this->seats_taken ?? 0), // filled by withCount in the controller
        ];
    }
}
