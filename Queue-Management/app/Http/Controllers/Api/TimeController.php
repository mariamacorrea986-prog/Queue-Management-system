<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

// The phone calls this to work out how far its clock is from the server's (the "clock offset"),
// which keeps arrived_at fair even if a student's phone clock is wrong.
class TimeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'server_time' => now()->utc()->toIso8601ZuluString('millisecond'),
            'timestamp_ms' => (int) round(microtime(true) * 1000), // easy for JavaScript to subtract from Date.now()
        ]);
    }
}
