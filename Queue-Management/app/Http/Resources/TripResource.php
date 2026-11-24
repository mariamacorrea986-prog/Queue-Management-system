<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'route_id' => $this->route_id,
            'bus_id' => $this->bus_id,
            'driver_id' => $this->driver_id,
            'departs_at' => $this->departs_at->toIso8601String(),
            'status' => $this->status,
            'route' => new RouteResource($this->whenLoaded('route')),
            'bus' => new BusResource($this->whenLoaded('bus')),
            // Only id and name of the driver: no email or other account details in a schedule listing.
            'driver' => $this->whenLoaded('driver', fn () => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
            ]),
        ];
    }
}
