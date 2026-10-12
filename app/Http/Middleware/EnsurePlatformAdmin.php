<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('platform')->check()) {
            return redirect()->guest(route('platform.login'));
        }

        return $next($request);
    }
}
