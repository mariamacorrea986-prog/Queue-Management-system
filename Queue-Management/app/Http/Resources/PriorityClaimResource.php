<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriorityClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'status' => $this->status,
            'valid_until' => $this->valid_until?->toDateString(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'document' => [
                'available' => (bool) $this->document_path,
                'original_name' => $this->document_original_name,
                'mime_type' => $this->document_mime_type,
                'size_bytes' => $this->document_size,
            ],
            'created_at' => $this->created_at->toIso8601String(),
            // Included only for the operator list, where the controller loads the student.
            'student' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'student_id' => $this->user->student_id,
            ]),
        ];
    }
}
