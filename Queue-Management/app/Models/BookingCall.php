<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCall extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'trip_id', 'called_by', 'seat_type', 'called_at', 'deadline_at', 'outcome', 'outcome_at'];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
            'deadline_at' => 'datetime',
            'outcome_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'called_by');
    }
}
