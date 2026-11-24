<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCall;
use Illuminate\Support\Facades\DB;

// Nightly: a booking from an earlier day that is still "active" would block the student from booking again.
class StaleBookingService
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function expireStale(): int
    {
        $expired = 0;

        // chunkById reads the old bookings in small batches, so a large backlog does not use much memory.
        Booking::whereIn('status', Booking::ACTIVE_STATUSES)
            ->where('queue_date', '<', today()->toDateString())
            ->chunkById(200, function ($bookings) use (&$expired) {
                foreach ($bookings as $booking) {
                    DB::transaction(function () use ($booking, &$expired) {
                        $booking = Booking::lockForUpdate()->find($booking->id);

                        if (! $booking || ! $booking->isActive()) {
                            return; // somebody changed it a moment ago: leave it alone
                        }

                        // A call that was never answered counts as skipped, which also frees the unique "pending call" slot.
                        BookingCall::where('booking_id', $booking->id)->where('outcome', 'pending')
                            ->update(['outcome' => 'skipped', 'outcome_at' => now()]);

                        $booking->forceFill(['status' => 'expired'])->save();
                        $expired++;
                    });
                }
            });

        if ($expired > 0) {
            $this->audit->log(null, 'bookings.expired_stale', null, null, ['count' => $expired]); // done by the system
        }

        return $expired;
    }
}
