<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelTripRequest;
use App\Http\Requests\TripFilterRequest;
use App\Http\Requests\TripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\TripScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Thin controller: validation is in the Form Requests, the rules and audit logging are in TripScheduleService.
class TripController extends Controller
{
    public function __construct(private TripScheduleService $schedule)
    {
    }

    public function index(TripFilterRequest $request): AnonymousResourceCollection
    {
        $trips = Trip::with(['route.origin', 'route.destination', 'bus', 'driver'])
            ->when($request->filled('route_id'), fn ($q) => $q->where('route_id', $request->route_id))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('departs_at', $request->date))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('departs_at')
            ->paginate($request->integer('per_page', 15));

        return TripResource::collection($trips);
    }

    public function store(TripRequest $request): JsonResponse
    {
        $trip = $this->schedule->create($request->tripData(), $request->user(), $request->ip());

        return (new TripResource($this->withRelations($trip)))->response()->setStatusCode(201);
    }

    public function update(TripRequest $request, Trip $trip): TripResource
    {
        $trip = $this->schedule->update($trip, $request->tripData(), $request->user(), $request->ip());

        return new TripResource($this->withRelations($trip));
    }

    public function cancel(CancelTripRequest $request, Trip $trip): TripResource
    {
        $trip = $this->schedule->cancel($trip, $request->input('reason'), $request->user(), $request->ip());

        return new TripResource($this->withRelations($trip));
    }

    private function withRelations(Trip $trip): Trip
    {
        return $trip->load(['route.origin', 'route.destination', 'bus', 'driver']);
    }
}
