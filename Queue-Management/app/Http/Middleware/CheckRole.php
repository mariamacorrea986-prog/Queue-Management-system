<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Usage on a route: ->middleware('role:operator,admin') means "operator OR admin".
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // The role always comes from the authenticated user in the database, never from the request.
        if (! $request->user() || ! $request->user()->hasRole(...$roles)) {
            abort(403, 'You do not have permission to do this.');
        }

        return $next($request);
    }
}
