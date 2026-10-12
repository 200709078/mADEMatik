<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function login(): View { return view('auth.login'); }
    public function register(): View { return view('auth.register'); }
    public function forgotPassword(): View { return view('auth.forgot-password'); }
    public function resetPassword(Request $request, string $token): View { return view('auth.reset-password', ['token' => $token, 'email' => $request->string('email')->toString()]); }
    public function verifyEmail(Request $request): View|RedirectResponse { return $request->user()->hasVerifiedEmail() ? redirect()->route('profile.edit') : view('auth.verify-email'); }
    public function confirmPassword(): View { return view('auth.confirm-password'); }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string']]);
        $key = 'login:'.hash('sha256', Str::lower($credentials['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => ceil(RateLimiter::availableIn($key) / 60)])]);
        }
        $user = User::where('email', Str::lower($credentials['email']))->first();
        if (! $user || $user->is_blocked || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        Auth::login($user, $request->boolean('remember'));
        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect()->intended(route('profile.edit'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'terms' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ]);
        $user = User::create(['name' => $data['name'], 'email' => Str::lower($data['email']), 'password' => Hash::make($data['password']), 'role' => 'member', 'locale' => 'tr']);
        $user->notificationPreference()->create(['comment_mail' => false, 'reply_mail' => false, 'project_mail' => false, 'site_notifications' => true]);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('verification.notice')->with('status', 'Hesabınız oluşturuldu. E-posta adresinizi doğrulayarak yorum yazabilirsiniz.');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:254']]);
        Password::sendResetLink(['email' => Str::lower($request->input('email'))]);
        return back()->with('status', 'Bu adresle kayıtlı bir hesap varsa parola sıfırlama bağlantısı gönderildi.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::defaults()]]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            ActivityLog::record('user.password_reset', $user);
            event(new PasswordReset($user));
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }
        return redirect()->route('login')->with('status', __($status));
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();
        return redirect()->route('profile.edit')->with('status', 'E-posta adresiniz doğrulandı.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) { return redirect()->route('profile.edit'); }
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'Yeni doğrulama bağlantısı e-posta adresinize gönderildi.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }
        $request->session()->put('auth.password_confirmed_at', time());
        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
