<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('trip_id')->constrained('trips')->restrictOnDelete();
            $table->foreignId('called_by')->constrained('users')->restrictOnDelete(); // the driver
            $table->enum('seat_type', ['general', 'priority']);
            $table->dateTime('called_at');
            $table->dateTime('deadline_at');
            $table->enum('outcome', ['pending', 'boarded', 'skipped'])->default('pending');
            $table->dateTime('outcome_at')->nullable();

            // Same trick as bookings.active_user_id: only a PENDING call holds the booking id,
            // so two drivers can never have the same student called at the same time.
            $table->unsignedBigInteger('pending_booking_id')->nullable()
                ->virtualAs("CASE WHEN outcome = 'pending' THEN booking_id ELSE NULL END");

            $table->timestamps();

            $table->unique('pending_booking_id');
            $table->unique(['booking_id', 'trip_id']); // a student is called at most once per trip
            $table->index(['trip_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_calls');
    }
};
