<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminSessionTimeout
{
    /** Idle timeout in minutes. */
    protected const TIMEOUT_MINUTES = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->check()) {
            return $next($request);
        }

        $key = 'admin_last_activity';
        $now = now();
        $last = $request->session()->get($key);

        if ($last !== null && $now->diffInMinutes($last) >= self::TIMEOUT_MINUTES) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->with('status', __('You have been logged out due to inactivity.'));
        }

        $request->session()->put($key, $now);

        return $next($request);
    }
}
