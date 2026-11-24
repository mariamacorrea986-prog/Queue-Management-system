<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CallNextRequest;
use App\Http\Requests\DriverTripFilterRequest;
use App\Http\Resources\CalledStudentResource;
use App\Http\Resources\DriverTripResource;
use App\Http\Resources\TripResource;
use App\Http\Resources\WaitingStudentResource;
use App\Models\Trip;
use App\Services\CallBatchService;
use App\Services\ManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DriverTripController extends Controller
{
    // GET /driver/trips: only the logged-in driver's trips, for one day (today unless ?date= is given).
    public function index(DriverTripFilterRequest $request): AnonymousResourceCollection
    {
        $trips = Trip::where('driver_id', $request->user()->id)
            ->whereDate('departs_at', $request->input('date', today()->toDateString()))
            ->with(['route.origin', 'route.destination', 'bus'])
            ->withCount(['bookingCalls as seats_taken' => fn ($q) => $q->whereIn('outcome', ['pending', 'boarded'])])
            ->orderBy('departs_at')
            ->get();

        return DriverTripResource::collection($trips);
    }

    // GET /driver/trips/{trip}/manifest
    public function manifest(Trip $trip, ManifestService $manifest): JsonResponse
    {
        Gate::authorize('manage', $trip); // 403 if this is another driver's trip

        $data = $manifest->for($trip);

        return response()->json(['data' => [
            'trip' => new TripResource($trip),
            'seats' => $data['seats'],
            'called' => CalledStudentResource::collection($data['calls']),
            'waiting' => WaitingStudentResource::collection($data['waiting']),
        ]]);
    }

    // POST /driver/trips/{trip}/call-next
    public function callNext(CallNextRequest $request, Trip $trip, CallBatchService $batches): AnonymousResourceCollection
    {
        Gate::authorize('manage', $trip);

        $calls = $batches->callNext($trip, $request->user(), $request->integer('count') ?: null);

        return CalledStudentResource::collection($calls)->additional(['meta' => ['called_count' => $calls->count()]]);
    }
}
