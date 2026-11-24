<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Business rules for the bus schedule. Controllers only call these methods.
class TripScheduleService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    // The overview says "no overlapping trips" but trips have no end time, so overlap is defined as:
    // two trips of the same bus (or driver) may not depart less than this many minutes apart.
    public function minGapMinutes(): int
    {
        return Setting::getInt('trip_min_gap_minutes', 60);
    }

    // $column is 'bus_id' or 'driver_id'. Cancelled trips are ignored: they free the bus and driver.
    public function hasConflict(string $column, int $id, Carbon $departsAt, ?int $ignoreTripId = null): bool
    {
        $gap = $this->minGapMinutes();

        return Trip::where($column, $id)
            ->where('status', '!=', 'cancelled')
            ->when($ignoreTripId, fn ($query) => $query->where('id', '!=', $ignoreTripId))
            ->where('departs_at', '>', $departsAt->copy()->subMinutes($gap))
            ->where('departs_at', '<', $departsAt->copy()->addMinutes($gap))
            ->exists();
    }

    public function create(array $data, User $actor, ?string $ip): Trip
    {
        // The trip and its audit entry are saved together or not at all.
        return DB::transaction(function () use ($data, $actor, $ip) {
            $trip = Trip::create($data)->refresh(); // refresh() loads the default status

            $this->audit->log($actor, 'trip.created', $trip, null, [
                'route_id' => $trip->route_id,
                'bus_id' => $trip->bus_id,
                'driver_id' => $trip->driver_id,
                'departs_at' => $trip->departs_at->toIso8601String(),
            ], $ip);

            return $trip;
        });
    }

    public function update(Trip $trip, array $data, User $actor, ?string $ip): Trip
    {
        return DB::transaction(function () use ($trip, $data, $actor, $ip) {
            // Lock the row so an update and a cancel at the same moment cannot both win.
            $trip = Trip::lockForUpdate()->findOrFail($trip->id);
            $this->assertScheduled($trip);

            $trip->fill($data);
            $changes = [];
            foreach ($trip->getDirty() as $field => $new) {
                $changes[$field] = [
                    'from' => $this->plain($trip->getOriginal($field)),
                    'to' => $this->plain($trip->{$field}),
                ];
            }

            if ($changes !== []) {
                $trip->save();
                $this->audit->log($actor, 'trip.updated', $trip, null, ['changes' => $changes], $ip);
            }

            return $trip;
        });
    }

    public function cancel(Trip $trip, ?string $reason, User $actor, ?string $ip): Trip
    {
        return DB::transaction(function () use ($trip, $reason, $actor, $ip) {
            $trip = Trip::lockForUpdate()->findOrFail($trip->id);
            $this->assertScheduled($trip);

            $trip->status = 'cancelled';
            $trip->save();

            $this->audit->log($actor, 'trip.cancelled', $trip, $reason, [], $ip);

            return $trip;
        });
    }

    // 409 Conflict: the request is valid, but the trip's current state does not allow it.
    private function assertScheduled(Trip $trip): void
    {
        if ($trip->status !== 'scheduled') {
            abort(409, "Only a scheduled trip can be changed or cancelled (this trip is {$trip->status}).");
        }
    }

    // Turns dates into text so they can be stored in the audit log's JSON column.
    private function plain(mixed $value): mixed
    {
        return $value instanceof Carbon ? $value->toIso8601String() : $value;
    }
}
