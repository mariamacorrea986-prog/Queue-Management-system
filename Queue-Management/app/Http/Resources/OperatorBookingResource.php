<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// A booking as operators and admins see it. Deliberately NOT included:
//  - the phone's coordinates (location is personal data),
//  - the priority category (viewing it must be audited, see the priority claim endpoints). Only the lane is shown.
class OperatorBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => ['id' => $this->user->id, 'name' => $this->user->name, 'student_id' => $this->user->student_id],
            'route' => ['id' => $this->route->id, 'name' => $this->route->name],
            'lane' => $this->lane,
            'status' => $this->status,
            'queue_date' => $this->queue_date,
            'queue_number' => $this->queue_number,
            'queue_label' => $this->queueLabel(),
            'arrived_at' => $this->arrived_at->format('Y-m-d\TH:i:s.v\Z'),
            'confirmed_at' => $this->confirmed_at?->format('Y-m-d\TH:i:s.v\Z'),
            'manual' => $this->checked_in_by !== null,
            'checked_in_by' => $this->checkedInBy?->name,
        ];
    }
}
