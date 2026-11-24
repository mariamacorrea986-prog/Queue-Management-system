<?php

namespace App\Http\Resources;

use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        [$min, $max] = SettingService::LIMITS[$this->name] ?? [null, null];

        return [
            'name' => $this->name,
            'value' => (int) $this->value,
            'description' => $this->description,
            // The allowed range, so the admin screen can validate before sending.
            'min' => $min,
            'max' => $max,
        ];
    }
}
