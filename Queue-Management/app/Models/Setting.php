<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'value', 'description'];

    // Called after an admin changes a setting, so the new value is used straight away instead of after 60 seconds.
    public static function forgetCache(string $name): void
    {
        Cache::forget("setting:{$name}");
    }

    // Thresholds are read through this helper, never hard-coded elsewhere.
    // Cached for 60 seconds so a busy check-in does not query the settings table every time.
    public static function getInt(string $name, int $default = 0): int
    {
        $value = Cache::remember("setting:{$name}", 60, fn () => static::where('name', $name)->value('value'));

        return $value === null ? $default : (int) $value;
    }
}
