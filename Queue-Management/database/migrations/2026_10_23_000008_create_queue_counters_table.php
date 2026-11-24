<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per route, day and lane. The confirmation job locks this row so numbers are never duplicated.
        Schema::create('queue_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->restrictOnDelete();
            $table->date('queue_date');
            $table->enum('lane', ['general', 'priority']);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['route_id', 'queue_date', 'lane']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_counters');
    }
};
