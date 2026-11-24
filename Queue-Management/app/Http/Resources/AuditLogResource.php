<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            // Null for actions done by the system itself (nightly jobs).
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name, 'role' => $this->user->role] : null,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null, // "Trip", not "App\Models\Trip"
            'subject_id' => $this->subject_id,
            'reason' => $this->reason,
            'details' => $this->details,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
