<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingFilterRequest;
use App\Http\Requests\CheckInRequest;
use App\Http\Requests\ManualCheckInRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\OperatorBookingResource;
use App\Models\Booking;
use App\Services\BookingCancellationService;
use App\Services\BookingCheckInService;
use App\Services\BookingQueueService;
use App\Services\ManualCheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    // POST /bookings: the student's own check-in. 201 for a new booking, 200 when the same request_id was already processed.
    public function store(CheckInRequest $request, BookingCheckInService $service): JsonResponse
    {
        [$booking, $created] = $service->checkIn($request->user(), $request->validated());

        $booking->load(['route.origin', 'route.destination', 'calls.trip']);

        return (new BookingResource($booking))->response()->setStatusCode($created ? 201 : 200);
    }

    // GET /bookings/current: the student's active booking, or {"data": null} when they have none.
    // Returning null with 200 (not 404) means "no booking yet" is a normal state, not an error, on the phone.
    public function current(Request $request, BookingQueueService $queue): JsonResponse
    {
        $booking = $queue->currentFor($request->user());

        return response()->json(['data' => $booking ? new BookingResource($booking) : null]);
    }

    // GET /bookings/mine: the student's own booking history, newest first.
    public function mine(Request $request): AnonymousResourceCollection
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with(['route.origin', 'route.destination', 'calls.trip'])
            ->latest('id')
            ->paginate(15);

        return BookingResource::collection($bookings);
    }

    // POST /bookings/{booking}/cancel: a student cancels their own booking, before being called.
    public function cancel(Booking $booking, BookingCancellationService $service): BookingResource
    {
        Gate::authorize('cancel', $booking); // 403 if it is somebody else's booking

        $booking = $service->cancel($booking);

        return new BookingResource($booking->load(['route.origin', 'route.destination', 'calls.trip']));
    }

    // POST /bookings/manual: an operator checks a student in by hand.
    public function manual(ManualCheckInRequest $request, ManualCheckInService $service): JsonResponse
    {
        [$booking, $created] = $service->checkIn($request->user(), $request->validated(), $request->ip());

        $booking->load(['user:id,name,student_id', 'route:id,name', 'checkedInBy:id,name']);

        return (new OperatorBookingResource($booking))->response()->setStatusCode($created ? 201 : 200);
    }

    // GET /bookings: the booking list for operators and admins, with filters.
    public function index(BookingFilterRequest $request): AnonymousResourceCollection
    {
        $bookings = Booking::with(['user:id,name,student_id', 'route:id,name', 'checkedInBy:id,name'])
            ->when($request->filled('route_id'), fn ($q) => $q->where('route_id', $request->route_id))
            ->when($request->filled('date'), fn ($q) => $q->where('queue_date', $request->date))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('lane'), fn ($q) => $q->where('lane', $request->lane))
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->whereHas('user', function ($u) use ($request) {
                    $u->where('student_id', 'like', "%{$request->q}%")->orWhere('name', 'like', "%{$request->q}%");
                });
            })
            // Newest day first; inside a day, queue order (arrived_at, received_at, id).
            ->orderByDesc('queue_date')->orderBy('arrived_at')->orderBy('received_at')->orderBy('id')
            ->paginate($request->integer('per_page', 20));

        return OperatorBookingResource::collection($bookings);
    }
}
