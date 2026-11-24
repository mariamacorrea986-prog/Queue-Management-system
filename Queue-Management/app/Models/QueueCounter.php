<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueCounter extends Model
{
    use HasFactory;

    protected $fillable = ['route_id', 'queue_date', 'lane', 'last_number'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
