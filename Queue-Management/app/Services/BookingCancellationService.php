<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class BookingCancellationService
{
    // A student may cancel only before being called.
    public function cancel(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            // Lock the row. Call-next also locks the bookings it calls, so "cancel" and "called" can never both win:
            // whichever comes second sees the other's result.
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);

            if (! in_array($booking->status, ['provisional', 'confirmed'], true)) {
                abort(409, "This booking can no longer be cancelled (it is {$booking->status}).");
            }

            // A cancelled booking is no longer "active", so the student can check in again.
            // A confirmed booking keeps its old number: the gap is harmless, numbers are never reused.
            $booking->forceFill(['status' => 'cancelled'])->save();

            return $booking;
        });
    }
}
