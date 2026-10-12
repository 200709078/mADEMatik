<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnsureAccountActive
{
    public function handle(Request $request,Closure $next)
    {
        if ($request->user()?->is_blocked) {
            Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            abort(403,'Hesabınızın erişimi kısıtlandı. İletişim formundan destek isteyebilirsiniz.');
        }
        return $next($request);
    }
}
