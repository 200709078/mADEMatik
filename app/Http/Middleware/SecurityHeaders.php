<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class SecurityHeaders
{
    public function handle(Request $request,Closure $next)
    {
        $response=$next($request);
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('X-Frame-Options','SAMEORIGIN');
        $response->headers->set('Referrer-Policy','strict-origin-when-cross-origin');
        $accountPage = $request->routeIs('login','register','password.*','verification.*','profile.*','notifications.*','admin.*','comments.edit');
        if ($accountPage || $request->user() !== null) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($request->routeIs('password.*','verification.*')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }
        $geolocation = $request->routeIs('clock') ? '(self)' : '()';
        $response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation='.$geolocation);
        if (app()->isProduction() && $request->isSecure()) $response->headers->set('Strict-Transport-Security','max-age=31536000');
        return $response;
    }
}
