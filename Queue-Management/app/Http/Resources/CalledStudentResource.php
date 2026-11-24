<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// One call, as the driver sees it. The "Priority" tag is just a yes/no: never the reason.
class CalledStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'call_id' => $this->id,
            'booking_id' => $this->booking_id,
            'outcome' => $this->outcome, // pending, boarded or skipped
            'called_at' => $this->called_at->toIso8601String(),
            'deadline_at' => $this->deadline_at->toIso8601String(),
            'outcome_at' => $this->outcome_at?->toIso8601String(),
            'student' => ['name' => $this->booking->user->name, 'student_id' => $this->booking->user->student_id],
            'queue_label' => $this->booking->queueLabel(),
            'is_priority' => $this->booking->lane === 'priority',
            // Shows what happened to the booking after a skip: "confirmed" again, or "expired".
            'booking_status' => $this->booking->status,
        ];
    }
}
