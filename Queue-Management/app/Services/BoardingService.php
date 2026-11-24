<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCall;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

// The driver's two decisions about a student who was called: boarded, or skipped.
class BoardingService
{
    public function board(BookingCall $call): BookingCall
    {
        return DB::transaction(function () use ($call) {
            // Lock the call and the booking so a double tap or two devices cannot decide the same call twice.
            $call = BookingCall::lockForUpdate()->findOrFail($call->id);
            $this->assertPending($call);
            $booking = Booking::lockForUpdate()->findOrFail($call->booking_id);

            $call->forceFill(['outcome' => 'boarded', 'outcome_at' => now()])->save();
            $booking->forceFill(['status' => 'boarded'])->save();

            return $call->setRelation('booking', $booking);
        });
    }

    public function skip(BookingCall $call): BookingCall
    {
        return DB::transaction(function () use ($call) {
            $call = BookingCall::lockForUpdate()->findOrFail($call->id);
            $this->assertPending($call);

            // A student cannot be skipped before their time is up. This stops a driver from dropping
            // someone early, and it is what the deadline is for.
            if (now()->lt($call->deadline_at)) {
                abort(409, 'The student still has time to board (until ' . $call->deadline_at->format('H:i:s') . ' UTC).');
            }

            $booking = Booking::lockForUpdate()->findOrFail($call->booking_id);

            // Skips are counted from the call history, so no extra column is needed. This skip counts too.
            $skips = BookingCall::where('booking_id', $booking->id)->where('outcome', 'skipped')->count() + 1;

            $call->forceFill(['outcome' => 'skipped', 'outcome_at' => now()])->save();

            // Back to "confirmed" with the same queue number, or "expired" after too many skips.
            // Expired is not an active status, so the student is free to check in again.
            $booking->forceFill([
                'status' => $skips >= Setting::getInt('max_skips', 2) ? 'expired' : 'confirmed',
            ])->save();

            return $call->setRelation('booking', $booking);
        });
    }

    // 409: the request is valid, but this call has already been decided.
    private function assertPending(BookingCall $call): void
    {
        if ($call->outcome !== 'pending') {
            abort(409, "This call has already been decided (it is {$call->outcome}).");
        }
    }
}
