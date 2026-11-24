<?php

namespace App\Console\Commands;

use App\Services\LocationRetentionService;
use Illuminate\Console\Command;

class ClearOldLocations extends Command
{
    protected $signature = 'locations:clear';

    protected $description = 'Erase GPS coordinates older than the retention period (the bookings are kept)';

    public function handle(LocationRetentionService $service): int
    {
        $this->info("Cleared coordinates on {$service->clearOld()} booking(s).");

        return self::SUCCESS;
    }
}
