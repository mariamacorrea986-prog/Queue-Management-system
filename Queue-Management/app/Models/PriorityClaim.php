<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriorityClaim extends Model
{
    use HasFactory;

    // status, reviewed_by and reviewed_at are set only by operator actions, never by mass assignment from a request.
    protected $fillable = ['user_id', 'category', 'document_path', 'document_original_name', 'document_mime_type', 'document_size'];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // The one rule for "this claim gives the priority lane": approved, and not past its end date.
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', 'approved')->where(function ($q) {
            $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString());
        });
    }

    // A claim counts only if approved and not past its expiry date.
    public function isUsable(): bool
    {
        return $this->status === 'approved'
            && ($this->valid_until === null || ! $this->valid_until->isPast());
    }
}
