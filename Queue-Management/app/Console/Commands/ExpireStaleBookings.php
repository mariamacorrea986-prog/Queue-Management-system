<?php

namespace App\Console\Commands;

use App\Services\StaleBookingService;
use Illuminate\Console\Command;

class ExpireStaleBookings extends Command
{
    protected $signature = 'bookings:expire-stale';

    protected $description = 'Expire active bookings left over from earlier days so they do not block students';

    public function handle(StaleBookingService $service): int
    {
        $this->info("Expired {$service->expireStale()} stale booking(s).");

        return self::SUCCESS;
    }
}
