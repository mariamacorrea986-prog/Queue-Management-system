<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// A confirmed student who has not been called yet, as the driver sees it.
class WaitingStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'booking_id' => $this->id,
            'student' => ['name' => $this->user->name, 'student_id' => $this->user->student_id],
            'queue_label' => $this->queueLabel(),
            'is_priority' => $this->lane === 'priority',
            'skips' => $this->calls->where('outcome', 'skipped')->count(),
        ];
    }
}
