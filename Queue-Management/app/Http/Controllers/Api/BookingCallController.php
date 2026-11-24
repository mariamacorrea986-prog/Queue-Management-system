<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CalledStudentResource;
use App\Models\BookingCall;
use App\Services\BoardingService;
use Illuminate\Support\Facades\Gate;

class BookingCallController extends Controller
{
    // POST /booking-calls/{call}/board: the student got on the bus.
    public function board(BookingCall $call, BoardingService $boarding): CalledStudentResource
    {
        Gate::authorize('manage', $call); // only the driver of this call's trip

        return new CalledStudentResource($boarding->board($call)->load('booking.user:id,name,student_id'));
    }

    // POST /booking-calls/{call}/skip: the student missed the deadline.
    public function skip(BookingCall $call, BoardingService $boarding): CalledStudentResource
    {
        Gate::authorize('manage', $call);

        return new CalledStudentResource($boarding->skip($call)->load('booking.user:id,name,student_id'));
    }
}
