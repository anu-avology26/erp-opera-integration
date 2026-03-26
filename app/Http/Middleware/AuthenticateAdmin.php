<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        return redirect()->route('admin.login');
    }
}
