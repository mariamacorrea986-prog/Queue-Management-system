<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Route;
use App\Models\Station;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransportSeeder extends Seeder
{
    public function run(): void
    {
        // The demo station is the one you test check-in at; its position comes from .env.
        // The other coordinates are rough placeholders: correct them in the operator screens later.
        $demo = Station::updateOrCreate(['name' => 'Serrekunda Bus Station'], [
            'latitude' => config('demo.station_lat'),
            'longitude' => config('demo.station_lng'),
            'radius_m' => 100,
            'is_active' => true,
        ]);
        $brikama = Station::updateOrCreate(['name' => 'Brikama Bus Station'], [
            'latitude' => 13.2714, 'longitude' => -16.6494, 'radius_m' => 100, 'is_active' => true,
        ]);
        $campus = Station::updateOrCreate(['name' => 'UTG Faraba Campus'], [
            'latitude' => 13.2400, 'longitude' => -16.7000, 'radius_m' => 100, 'is_active' => true,
        ]);

        $toCampus = Route::updateOrCreate(['name' => 'Serrekunda to Campus'], [
            'origin_station_id' => $demo->id, 'destination_station_id' => $campus->id, 'is_active' => true,
        ]);
        Route::updateOrCreate(['name' => 'Brikama to Campus'], [
            'origin_station_id' => $brikama->id, 'destination_station_id' => $campus->id, 'is_active' => true,
        ]);
        Route::updateOrCreate(['name' => 'Campus to Serrekunda'], [
            'origin_station_id' => $campus->id, 'destination_station_id' => $demo->id, 'is_active' => true,
        ]);

        $bus1 = Bus::updateOrCreate(['plate_number' => 'BJL 1001'], ['capacity' => 50, 'priority_seats' => 5, 'status' => 'active']);
        $bus2 = Bus::updateOrCreate(['plate_number' => 'BJL 1002'], ['capacity' => 40, 'priority_seats' => 5, 'status' => 'active']);

        $driver1 = User::where('email', 'driver@utg.test')->firstOrFail();
        $driver2 = User::where('email', 'driver2@utg.test')->firstOrFail();

        // Trips for today and tomorrow, so there is always something to book against.
        foreach ([today(), today()->addDay()] as $day) {
            foreach ([[7, $bus1, $driver1], [8, $bus2, $driver2], [9, $bus1, $driver1]] as [$hour, $bus, $driver]) {
                Trip::updateOrCreate(
                    ['bus_id' => $bus->id, 'departs_at' => $day->copy()->setTime($hour, 0)],
                    ['route_id' => $toCampus->id, 'driver_id' => $driver->id, 'status' => 'scheduled']
                );
            }
        }
    }
}
