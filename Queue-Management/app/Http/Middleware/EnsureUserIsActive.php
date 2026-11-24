<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Checked on every request, not only at login, so an admin who deactivates an account
// cuts off that person's existing token immediately.
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            abort(403, 'This account has been deactivated.');
        }

        return $next($request);
    }
}
