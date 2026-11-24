<?php

// Demo station coordinates come from .env so you can set them to your own location
// and test check-in at your desk. env() is read here, in a config file, so it still works
// after `php artisan config:cache`.
return [
    'station_lat' => (float) env('DEMO_STATION_LAT', 13.4549),
    'station_lng' => (float) env('DEMO_STATION_LNG', -16.5790),
    'password' => env('DEMO_PASSWORD', 'Password123!'),
];
