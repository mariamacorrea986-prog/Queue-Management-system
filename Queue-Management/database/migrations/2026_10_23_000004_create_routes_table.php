<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // restrictOnDelete: a station used by a route can never be deleted, so history is kept.
            $table->foreignId('origin_station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignId('destination_station_id')->constrained('stations')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['origin_station_id', 'destination_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
