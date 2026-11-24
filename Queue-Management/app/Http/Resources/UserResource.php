<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Decides exactly which user fields leave the API. The password is never listed here.
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'student_id' => $this->student_id,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'has_consented' => $this->consent_given_at !== null, // the app blocks check-in until true
            'consent_given_at' => $this->consent_given_at?->toIso8601String(),
        ];
    }
}
