<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->restrictOnDelete();
            $table->foreignId('bus_id')->constrained('buses')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('departs_at');
            $table->enum('status', ['scheduled', 'boarding', 'departed', 'cancelled'])->default('scheduled');
            $table->timestamps();

            // The database itself stops one bus or one driver from having two trips at the same time.
            // (Overlapping, not just identical, times is checked later in Laravel validation.)
            $table->unique(['bus_id', 'departs_at']);
            $table->unique(['driver_id', 'departs_at']);
            $table->index(['route_id', 'departs_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
