<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep error details private even when an old config cache enabled debug.
        if ($this->app->isProduction()) config(['app.debug' => false]);
        // Laravel skips console event wiring in testing; database safety must still apply.
        if (app()->runningUnitTests()) app(\Illuminate\Contracts\Console\Kernel::class)->rerouteSymfonyCommandEvents();
        \Illuminate\Support\Facades\Gate::define('admin', fn (\App\Models\User $user) => $user->isSuperUser());
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(6)->mixedCase()->numbers()->symbols());
        if (! app()->isProduction()) config(['mail.default' => 'log']);
        \Illuminate\Support\Facades\View::composer(['layouts.app','layouts.admin'], function ($view) {
            try { $settings=\App\Services\Settings::allLocalized(); }
            catch (\Illuminate\Database\QueryException $e) { $settings=array_map(fn($v)=>$v['tr']??[], config('content')); }
            $view->with('siteSettings',$settings);
        });
        \Illuminate\Support\Facades\RateLimiter::for('login', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(hash('sha256',mb_strtolower((string)$request->input('email')).'|'.$request->ip())));
        \Illuminate\Support\Facades\RateLimiter::for('comments', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->user()?->id ?? $request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('contact', fn (\Illuminate\Http\Request $request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by($request->ip()));
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function($user,$url) {
            return (new \Illuminate\Notifications\Messages\MailMessage)->subject(__('mail.verify_subject'))->greeting(__('mail.greeting',['name'=>$user->name]))->line(__('mail.verify_body'))->action(__('mail.verify_action'),$url)->line(__('mail.ignore'))->salutation('mADEMatik Solutions');
        });
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function($user,$token) {
            $url=url(route('password.reset',['token'=>$token,'email'=>$user->email],false));
            return (new \Illuminate\Notifications\Messages\MailMessage)->subject(__('mail.reset_subject'))->greeting(__('mail.greeting',['name'=>$user->name]))->line(__('mail.reset_body'))->action(__('mail.reset_action'),$url)->line(__('mail.reset_expiry',['minutes'=>config('auth.passwords.users.expire')]))->line(__('mail.ignore'))->salutation('mADEMatik Solutions');
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandStarting::class, [\App\Services\DatabaseSafety::class,'guard']);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandStarting::class, [\App\Services\LocalDatabase::class,'startForServe']);
    }
}
