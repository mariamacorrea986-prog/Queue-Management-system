<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    // The three statuses that count as "active" (same list as the database's active_user_id column).
    public const ACTIVE_STATUSES = ['provisional', 'confirmed', 'called'];

    // Keep milliseconds when saving and reading dates. Without this, Laravel writes 'Y-m-d H:i:s'
    // and silently drops them, which would break the arrived_at ordering. The table's created_at
    // and updated_at are also datetime(3) so every date column on this table uses the same format.
    protected $dateFormat = 'Y-m-d H:i:s.v';

    // user_id, status, queue_number etc. are NOT fillable: they are set by services, never from a request body.
    protected $fillable = [];

    // queue_date is left as a plain 'Y-m-d' string (no date cast) so it is stored exactly as DATE.

    protected function casts(): array
    {
        return [
            'arrived_at' => 'datetime',
            'received_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_m' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function priorityClaim(): BelongsTo
    {
        return $this->belongsTo(PriorityClaim::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(BookingCall::class);
    }

    // "7" for the general lane, "P3" for the priority lane. Empty until the booking is confirmed.
    public function queueLabel(): ?string
    {
        if ($this->queue_number === null) {
            return null;
        }

        return ($this->lane === 'priority' ? 'P' : '') . $this->queue_number;
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
