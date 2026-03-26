<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('app.admin_key') ?: env('APP_ADMIN_KEY');
        if ($key === null || $key === '') {
            return $next($request);
        }

        $provided = $request->query('key') ?? $request->header('X-Admin-Key') ?? $request->bearerToken();
        if ($provided !== $key) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}
