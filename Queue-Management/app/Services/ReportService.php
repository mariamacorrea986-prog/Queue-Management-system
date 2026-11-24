<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Route;
use Illuminate\Support\Facades\DB;

// Reports for a date range. The range is on the booking's queue_date (the day the student arrived).
// All figures are computed straight from the bookings and booking_calls tables, so they always match the database.
class ReportService
{
    private const STATUSES = ['provisional', 'confirmed', 'called', 'boarded', 'expired', 'cancelled'];

    public function summary(string $from, string $to, ?int $routeId): array
    {
        $byStatus = $this->bookings($from, $to, $routeId)
            ->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        $statuses = [];
        foreach (self::STATUSES as $status) {
            $statuses[$status] = (int) ($byStatus[$status] ?? 0);
        }

        // Every call a driver made, grouped by what happened: boarded, skipped or still pending.
        $calls = DB::table('booking_calls')
            ->join('bookings', 'bookings.id', '=', 'booking_calls.booking_id')
            ->whereBetween('bookings.queue_date', [$from, $to])
            ->when($routeId, fn ($q) => $q->where('bookings.route_id', $routeId))
            ->select('booking_calls.outcome', DB::raw('count(*) as total'))
            ->groupBy('booking_calls.outcome')
            ->pluck('total', 'outcome');

        $boarded = (int) ($calls['boarded'] ?? 0);
        $skipped = (int) ($calls['skipped'] ?? 0);
        $decided = $boarded + $skipped;

        return [
            'from' => $from,
            'to' => $to,
            'bookings_issued' => array_sum($statuses), // every booking created in the range, whatever happened to it
            'by_status' => $statuses,
            'priority_bookings' => $this->bookings($from, $to, $routeId)->where('lane', 'priority')->count(),
            'manual_checkins' => $this->bookings($from, $to, $routeId)->whereNotNull('checked_in_by')->count(),
            // Waiting time = from the student's arrival (check-in) to the moment the driver marked them boarded.
            'average_wait_minutes' => $this->averageWait($from, $to, $routeId),
            'calls' => ['boarded' => $boarded, 'skipped' => $skipped, 'pending' => (int) ($calls['pending'] ?? 0)],
            // No-show rate = skipped calls as a percentage of decided calls (boarded + skipped). Empty when no call was decided yet.
            'no_show_rate_percent' => $decided > 0 ? round($skipped / $decided * 100, 1) : null,
        ];
    }

    // Number of bookings per hour of arrival (0 to 23), with empty hours shown as 0.
    public function hourly(string $from, string $to, ?int $routeId): array
    {
        $rows = $this->bookings($from, $to, $routeId)
            ->select(DB::raw('HOUR(arrived_at) as hour'), DB::raw('count(*) as total'))
            ->groupBy('hour')
            ->pluck('total', 'hour');

        $hours = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hours[] = ['hour' => $hour, 'label' => sprintf('%02d:00', $hour), 'bookings' => (int) ($rows[$hour] ?? 0)];
        }

        return ['from' => $from, 'to' => $to, 'hours' => $hours];
    }

    public function byRoute(string $from, string $to, ?int $routeId): array
    {
        $counts = $this->bookings($from, $to, $routeId)
            ->select('route_id', DB::raw('count(*) as total'), DB::raw("SUM(CASE WHEN status = 'boarded' THEN 1 ELSE 0 END) as boarded"))
            ->groupBy('route_id')
            ->get()
            ->keyBy('route_id');

        $waits = $this->waitQuery($from, $to, $routeId)
            ->select('bookings.route_id', DB::raw('AVG(TIMESTAMPDIFF(SECOND, bookings.arrived_at, booking_calls.outcome_at)) as seconds'))
            ->groupBy('bookings.route_id')
            ->pluck('seconds', 'route_id');

        $routes = Route::when($routeId, fn ($q) => $q->where('id', $routeId))->orderBy('name')->get();

        return [
            'from' => $from,
            'to' => $to,
            'routes' => $routes->map(fn ($route) => [
                'route_id' => $route->id,
                'name' => $route->name,
                'bookings' => (int) ($counts[$route->id]->total ?? 0),
                'boarded' => (int) ($counts[$route->id]->boarded ?? 0),
                'average_wait_minutes' => isset($waits[$route->id]) ? round($waits[$route->id] / 60, 1) : null,
            ])->values()->all(),
        ];
    }

    private function bookings(string $from, string $to, ?int $routeId)
    {
        return Booking::query()
            ->whereBetween('queue_date', [$from, $to])
            ->when($routeId, fn ($q) => $q->where('route_id', $routeId));
    }

    // Boarded calls with their booking: the base of every waiting-time figure.
    private function waitQuery(string $from, string $to, ?int $routeId)
    {
        return DB::table('bookings')
            ->join('booking_calls', 'booking_calls.booking_id', '=', 'bookings.id')
            ->where('booking_calls.outcome', 'boarded')
            ->whereBetween('bookings.queue_date', [$from, $to])
            ->when($routeId, fn ($q) => $q->where('bookings.route_id', $routeId));
    }

    private function averageWait(string $from, string $to, ?int $routeId): ?float
    {
        $seconds = $this->waitQuery($from, $to, $routeId)
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, bookings.arrived_at, booking_calls.outcome_at)'));

        return $seconds === null ? null : round($seconds / 60, 1);
    }
}
