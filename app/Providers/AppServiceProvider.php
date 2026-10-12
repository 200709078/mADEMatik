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
        \Illuminate\Support\Facades\Gate::define('admin', fn (\App\Models\User $user) => $user->isSuperUser());
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols());
        if (! app()->isProduction()) config(['mail.default' => 'log']);
        config(['app.timezone' => 'Europe/Istanbul']);
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
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandStarting::class, function($event) {
            if (in_array($event->command,['db:wipe','migrate:fresh','migrate:refresh','migrate:reset'],true)) throw new \RuntimeException('Veri koruma kuralı: toplu/yıkıcı veritabanı komutları bu projede engellidir.');
            if (in_array($event->command,['migrate','migrate:rollback','db:seed','madematik:create-super-user'],true)) {
                $connection=config('database.default'); $db=config('database.connections.'.$connection);
                if ($connection !== 'mysql' || config('site.db_ownership') !== 'madematik_v2' || !preg_match('/^madematik_v2(?:_[a-z0-9_]+)?$/',$db['database']??'') || in_array($db['username']??'',['','root'],true)) throw new \RuntimeException('Veritabanı sahipliği doğrulanmadı. Yalnız yeni mADEMatik veritabanına işlem yapılabilir.');
                if (app()->environment('local') && ($db['unix_socket']??'') !== storage_path('runtime/mariadb.sock')) throw new \RuntimeException('Yerel geliştirmede yalnız projeye ait MariaDB soketi kullanılabilir.');
            }
        });
    }
}
