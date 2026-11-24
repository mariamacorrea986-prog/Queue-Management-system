<?php

namespace App\Http\Resources;

use App\Models\Setting;
use App\Services\BookingQueueService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isProvisional = $this->status === 'provisional';
        $call = $this->relationLoaded('calls') ? $this->calls->firstWhere('outcome', 'pending') : null;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'lane' => $this->lane,
            'route' => new RouteResource($this->whenLoaded('route')),
            // Queue number exists only after confirmation. The label is "P3" for the priority lane, "3" for general.
            'queue_number' => $this->queue_number,
            'queue_label' => $this->queue_number === null ? null
                : ($this->lane === 'priority' ? 'P' : '') . $this->queue_number,
            // Provisional position is "not final yet": it can still change until confirmation.
            'provisional_position' => $isProvisional ? app(BookingQueueService::class)->positionOf($this->resource) : null,
            'arrived_at' => $this->arrived_at->format('Y-m-d\TH:i:s.v\Z'), // always UTC with milliseconds
            // When the number will be assigned, so the phone can show a countdown.
            'confirms_at' => $isProvisional
                ? $this->arrived_at->copy()->addMinutes(Setting::getInt('confirmation_window_minutes', 5))->format('Y-m-d\TH:i:s.v\Z')
                : null,
            'confirmed_at' => $this->confirmed_at?->format('Y-m-d\TH:i:s.v\Z'),
            // The call notice (when a driver has called this student): shown with its deadline.
            'call' => $call ? [
                'deadline_at' => $call->deadline_at->toIso8601String(),
                'trip_id' => $call->trip_id,
                'departs_at' => $call->trip?->departs_at->toIso8601String(),
            ] : null,
        ];
    }
}
