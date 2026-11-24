<?php

namespace App\Console\Commands;

use App\Services\BookingConfirmationService;
use Illuminate\Console\Command;

class ConfirmBookings extends Command
{
    protected $signature = 'bookings:confirm';

    protected $description = 'Give queue numbers to provisional bookings that are old enough, in arrival order';

    // Thin on purpose: the work is in BookingConfirmationService, so tests can call it directly too.
    public function handle(BookingConfirmationService $service): int
    {
        $count = $service->confirmDue();

        $this->info("Confirmed {$count} booking(s).");

        return self::SUCCESS;
    }
}
