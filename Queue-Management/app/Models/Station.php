<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'latitude', 'longitude', 'radius_m', 'is_active'];

    protected function casts(): array
    {
        return [
            // Cast to float so the haversine maths later works with numbers, not strings.
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_m' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function routesFrom(): HasMany
    {
        return $this->hasMany(Route::class, 'origin_station_id');
    }

    public function routesTo(): HasMany
    {
        return $this->hasMany(Route::class, 'destination_station_id');
    }
}
