<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>@include('layouts.head')</head>
<body>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 button button-primary">{{ __('ui.skip') }}</a>
    <header class="surface sticky top-0 z-40 border-b border-token" x-data="{menuOpen:false}" @keydown.escape.window="menuOpen=false">
        <div class="container-site flex min-h-22 items-center justify-between gap-4">
            <a href="{{ route('home') }}" aria-label="mADEMatik Solutions · {{ __('ui.home') }}" class="shrink-0">
                <x-brand-logo class="h-auto w-32 min-[360px]:w-44 sm:w-56" />
            </a>
            <nav class="hidden items-center gap-1 xl:flex" aria-label="{{ __('ui.menu') }}">
                @foreach(['home','about','projects.index','clock','contact'] as $navRoute)
                    @php($navKey = str_replace('.index', '', $navRoute))
                    <a href="{{ route($navRoute) }}" class="nav-link" @if(request()->routeIs($navRoute) || ($navKey === 'projects' && request()->routeIs('projects.show'))) aria-current="page" @endif>{{ data_get($siteSettings ?? [], 'navigation.'.$navKey, __('ui.'.$navKey)) }}</a>
                @endforeach
            </nav>
            <div class="flex items-center gap-1 sm:gap-2">
                <x-theme-toggle />
                @auth
                    <x-notification-bell />
                    <div class="hidden sm:block"><x-ui.dropdown><x-slot:trigger><x-ui.avatar :name="auth()->user()->name" :src="auth()->user()->avatar_url" size="sm" /><span class="max-w-28 truncate">{{ auth()->user()->name }}</span></x-slot:trigger>
                        <a href="{{ route('profile.edit') }}" class="nav-link block">{{ __('ui.profile') }}</a>
                        @if(auth()->user()->isSuperUser())<a href="{{ route('admin.dashboard') }}" class="nav-link block">{{ __('ui.admin') }}</a>@endif
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link block w-full text-left" type="submit">{{ __('ui.logout') }}</button></form>
                    </x-ui.dropdown></div>
                @else
                    <x-ui.button :href="route('login')" variant="ghost" size="sm" class="hidden sm:inline-flex">{{ __('ui.login') }}</x-ui.button>
                @endauth
                <button type="button" class="button button-ghost button-icon xl:hidden" @click="menuOpen=!menuOpen" :aria-expanded="menuOpen" aria-controls="mobile-nav" aria-label="{{ __('ui.menu') }}"><span x-show="!menuOpen"><x-icon name="menu" /></span><span x-cloak x-show="menuOpen"><x-icon name="x" /></span></button>
            </div>
        </div>
        <nav id="mobile-nav" x-cloak x-show="menuOpen" class="container-site flex flex-col gap-1 border-t border-token py-4 xl:hidden" aria-label="{{ __('ui.menu') }}">
            @foreach(['home','about','projects.index','clock','contact'] as $navRoute)
                @php($navKey = str_replace('.index', '', $navRoute))
                <a href="{{ route($navRoute) }}" class="nav-link" @if(request()->routeIs($navRoute) || ($navKey === 'projects' && request()->routeIs('projects.show'))) aria-current="page" @endif>{{ data_get($siteSettings ?? [], 'navigation.'.$navKey, __('ui.'.$navKey)) }}</a>
            @endforeach
            <div class="mt-2 flex flex-wrap gap-2 border-t border-token pt-3">
                @auth
                    <x-ui.button :href="route('profile.edit')" variant="secondary" size="sm">{{ __('ui.profile') }}</x-ui.button>
                    @if(auth()->user()->isSuperUser())<x-ui.button :href="route('admin.dashboard')" variant="outline" size="sm">{{ __('ui.admin') }}</x-ui.button>@endif
                    <form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button type="submit" variant="ghost" size="sm">{{ __('ui.logout') }}</x-ui.button></form>
                @else
                    <x-ui.button :href="route('login')" variant="outline" size="sm">{{ __('ui.login') }}</x-ui.button><x-ui.button :href="route('register')" size="sm">{{ __('ui.register') }}</x-ui.button>
                @endauth
            </div>
        </nav>
    </header>
    @include('layouts.flash')
    <main id="main-content" tabindex="-1">@yield('content')</main>
    <footer class="surface border-t border-token pt-12 pb-6">
        <div class="container-site grid gap-10 sm:grid-cols-2 lg:grid-cols-3">
            <div><a href="{{ route('home') }}"><x-brand-logo loading="lazy" class="w-54" /></a><p class="text-muted mt-5 max-w-sm text-sm leading-7">{{ data_get($siteSettings ?? [], 'footer.description', __('ui.footer_note')) }}</p></div>
            <nav class="flex flex-col items-start gap-3 text-sm" aria-label="{{ __('ui.projects') }}"><a class="text-muted hover:text-[var(--brand-text)]" href="{{ route('about') }}">{{ __('ui.about') }}</a><a class="text-muted hover:text-[var(--brand-text)]" href="{{ route('projects.index') }}">{{ __('ui.projects') }}</a><a class="text-muted hover:text-[var(--brand-text)]" href="{{ route('comments.index') }}">{{ __('ui.comments') }}</a><a class="text-muted hover:text-[var(--brand-text)]" href="{{ route('contact') }}">{{ __('ui.contact') }}</a></nav>
            <div><p class="mb-4 text-sm font-semibold">{{ __('ui.other_connections') }}</p><nav class="flex flex-wrap gap-3" aria-label="{{ __('ui.social') }}">@foreach(data_get($siteSettings ?? [], 'social', []) as $social)<a href="{{ $social['url'] }}" class="link text-sm" target="_blank" rel="noopener noreferrer">{{ $social['label'] }}</a>@endforeach</nav><a href="https://awa.madematik.com" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-2 text-sm text-muted">aWa <x-icon name="external-link" class="h-4 w-4" /></a></div>
        </div>
        <div class="container-site mt-10 flex flex-wrap justify-between gap-4 border-t border-token pt-6 text-xs text-muted">
            <p>© {{ now()->year }} {{ data_get($siteSettings ?? [], 'footer.copyright', 'mADEMatik Solutions. '.__('ui.all_rights')) }}</p>
            <div class="flex flex-wrap gap-4"><a href="{{ route('legal.privacy') }}">{{ __('ui.privacy') }}</a><a href="{{ route('legal.terms') }}">{{ __('ui.terms') }}</a><a href="{{ route('legal.membership') }}">{{ __('ui.membership') }}</a></div>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
