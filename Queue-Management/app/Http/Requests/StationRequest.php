<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Used for both create (POST) and update (PUT).
class StationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the role:operator middleware already protects the route
    }

    public function rules(): array
    {
        $station = $this->route('station'); // null when creating

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('stations', 'name')->ignore($station)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            // 10 m to 1 km is my own sanity range: smaller than GPS accuracy is useless, larger is no geofence.
            'radius_m' => ['sometimes', 'integer', 'between:10,1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
