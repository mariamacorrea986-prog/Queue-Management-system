<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('route_id')->constrained('routes')->restrictOnDelete();
            $table->enum('lane', ['general', 'priority'])->default('general');
            $table->foreignId('priority_claim_id')->nullable()->constrained('priority_claims')->restrictOnDelete();

            // Unique per check-in attempt, so a retry after a network failure never creates a second booking.
            $table->uuid('request_id')->unique();

            // datetime(3) = millisecond precision, so two taps in the same second can still be ordered.
            $table->dateTime('arrived_at', 3);   // when the student tapped, according to the phone
            $table->dateTime('received_at', 3);  // when the server got the request

            // Nullable: manual check-ins have no GPS reading, and coordinates are erased after the retention period.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();

            $table->enum('status', ['provisional', 'confirmed', 'called', 'boarded', 'expired', 'cancelled'])
                ->default('provisional');
            $table->date('queue_date');
            $table->unsignedInteger('queue_number')->nullable(); // stays empty until the confirmation job runs
            $table->dateTime('confirmed_at', 3)->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->restrictOnDelete(); // operator, for manual check-in

            // GENERATED column: holds user_id only while the booking is active, otherwise NULL.
            // A UNIQUE index on it means MySQL itself refuses a second active booking for the same student.
            // NULLs are ignored by unique indexes, so any number of finished bookings are allowed.
            $table->unsignedBigInteger('active_user_id')->nullable()
                ->virtualAs("CASE WHEN status IN ('provisional','confirmed','called') THEN user_id ELSE NULL END");

            // 3 digits here too, so created_at and updated_at match the format the Booking model uses.
            $table->timestamps(3);

            $table->unique('active_user_id');
            $table->unique(['route_id', 'queue_date', 'lane', 'queue_number'], 'bookings_queue_number_unique');
            $table->index(['route_id', 'lane', 'status', 'arrived_at'], 'bookings_queue_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
