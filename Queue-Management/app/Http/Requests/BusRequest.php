<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // PUT replaces the whole bus, so every field is required. That also lets us compare
    // priority_seats with the capacity being sent, not an old value.
    public function rules(): array
    {
        $bus = $this->route('bus'); // null when creating

        return [
            'plate_number' => ['required', 'string', 'max:20', Rule::unique('buses', 'plate_number')->ignore($bus)],
            'capacity' => ['required', 'integer', 'between:1,200'],
            'priority_seats' => ['required', 'integer', 'min:0', 'lte:capacity'],
            'status' => ['required', Rule::in(['active', 'maintenance'])],
        ];
    }

    public function messages(): array
    {
        return ['priority_seats.lte' => 'Priority seats cannot be more than the bus capacity.'];
    }
}
