<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureEmailVerification extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if ($request->user() && ! config('auth.email_verification_enabled', true)) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
