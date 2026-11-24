<?php

namespace App\Http\Requests;

use App\Models\Bus;
use App\Models\Route;
use App\Models\User;
use App\Services\TripScheduleService;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Used for create (POST) and update (PUT). The rules the database cannot enforce live here.
class TripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => ['required', 'integer', 'exists:routes,id'],
            'bus_id' => ['required', 'integer', 'exists:buses,id'],
            // The driver must really be a driver: a student's or operator's id is rejected.
            'driver_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'driver')],
            'departs_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return ['driver_id.exists' => 'The selected driver must be an existing user with the driver role.'];
    }

    // The validated data, with departs_at converted to the app's time zone so it is stored consistently
    // even if the client sent an offset such as +01:00.
    public function tripData(): array
    {
        $data = $this->validated();
        $data['departs_at'] = $this->departsAt();

        return $data;
    }

    private function departsAt(): Carbon
    {
        return Carbon::parse($this->input('departs_at'))->setTimezone(config('app.timezone'));
    }

    // Checks that need database lookups run after the basic rules pass.
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return; // fix the basic mistakes first
            }

            $trip = $this->route('trip'); // null when creating
            $schedule = app(TripScheduleService::class);
            $departs = $this->departsAt();
            $busId = (int) $this->input('bus_id');
            $driverId = (int) $this->input('driver_id');
            $routeId = (int) $this->input('route_id');

            // When editing, a field that did not change is not re-checked
            // (a bus sent to maintenance later must not block editing its existing trips).
            $changed = fn (string $field, int $value) => $trip === null || $trip->{$field} !== $value;

            if ($trip === null || ! $trip->departs_at->equalTo($departs)) {
                if ($departs->isPast()) {
                    $validator->errors()->add('departs_at', 'The departure time must be in the future.');
                }
            }

            if ($changed('route_id', $routeId) && ! Route::find($routeId)->is_active) {
                $validator->errors()->add('route_id', 'This route is not active.');
            }

            if ($changed('bus_id', $busId) && Bus::find($busId)->status !== 'active') {
                $validator->errors()->add('bus_id', 'Buses in maintenance cannot get new trips.');
            }

            if ($changed('driver_id', $driverId) && ! User::find($driverId)->is_active) {
                $validator->errors()->add('driver_id', 'This driver account is deactivated.');
            }

            $gap = $schedule->minGapMinutes();
            if ($schedule->hasConflict('bus_id', $busId, $departs, $trip?->id)) {
                $validator->errors()->add('bus_id', "This bus already has a trip within {$gap} minutes of this time.");
            }
            if ($schedule->hasConflict('driver_id', $driverId, $departs, $trip?->id)) {
                $validator->errors()->add('driver_id', "This driver already has a trip within {$gap} minutes of this time.");
            }
        });
    }
}
