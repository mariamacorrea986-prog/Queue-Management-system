<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Order matters: trips need users, buses and routes to exist first.
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            UserSeeder::class,
            TransportSeeder::class,
        ]);
    }
}
