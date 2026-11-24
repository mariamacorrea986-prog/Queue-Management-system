<?php

use Illuminate\Support\Facades\Schedule;

// Runs every minute. withoutOverlapping(10): if a run is still going when the next minute starts, the new run is
// skipped, and the lock expires after 10 minutes so a crashed run cannot block confirmations for ever.
Schedule::command('bookings:confirm')->everyMinute()->withoutOverlapping(10);

// Nightly jobs, a few minutes apart just after midnight (server time):
Schedule::command('bookings:expire-stale')->dailyAt('00:05')->withoutOverlapping(30); // free students blocked by yesterday's bookings
Schedule::command('priority:expire')->dailyAt('00:10');                                // priority claims past their end date
Schedule::command('locations:clear')->dailyAt('00:20')->withoutOverlapping(30);        // coordinates older than the retention period
