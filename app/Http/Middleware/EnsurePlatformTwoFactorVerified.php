<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformTwoFactorVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('platform')->check()) {
            return redirect()->route('platform.login');
        }

        if (session('platform_2fa_verified') !== true) {
            return redirect()->route('platform.verify_2fa');
        }

        return $next($request);
    }
}
