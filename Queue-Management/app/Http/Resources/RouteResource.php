<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'origin_station_id' => $this->origin_station_id,
            'destination_station_id' => $this->destination_station_id,
            'is_active' => $this->is_active,
            // whenLoaded: included only if the controller loaded the relation, which avoids hidden extra queries.
            'origin' => new StationResource($this->whenLoaded('origin')),
            'destination' => new StationResource($this->whenLoaded('destination')),
        ];
    }
}
