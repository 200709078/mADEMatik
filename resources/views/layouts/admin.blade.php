<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>@include('layouts.head')</head>
<body>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 button button-primary">{{ __('ui.skip') }}</a>
    <div class="admin-grid" x-data="{navigationOpen:false}">
        <aside class="admin-sidebar">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('admin.dashboard') }}"><x-brand-logo class="w-48" /></a>
                <button type="button" class="button button-ghost button-icon lg:hidden" @click="navigationOpen=!navigationOpen" :aria-expanded="navigationOpen" aria-controls="admin-navigation" aria-label="{{ __('ui.menu') }}"><x-icon name="menu" /></button>
            </div>
            <p class="eyebrow mt-6 mb-4 hidden lg:block">{{ __('ui.admin') }}</p>
            <nav id="admin-navigation" class="mt-5 space-y-1" :class="navigationOpen ? 'block' : 'hidden lg:block'" aria-label="{{ __('ui.admin') }}">
                @foreach([
                    ['admin.dashboard','layout-dashboard','admin_dashboard'],['admin.projects.index','folder','projects'],['admin.categories.index','layers','admin_categories'],['admin.comments.index','message-square','admin_comments'],['admin.users.index','users','admin_users'],['admin.contact-messages.index','mail','admin_contacts'],['notifications.index','bell','notifications'],['admin.settings.edit','settings','admin_settings'],['admin.activity.index','activity','admin_activity']
                ] as [$adminRoute,$icon,$label])
                    <a href="{{ route($adminRoute) }}" class="nav-link" @if(request()->routeIs($adminRoute) || (str_ends_with($adminRoute, '.index') && request()->routeIs(str_replace('.index', '.*', $adminRoute)))) aria-current="page" @endif><x-icon :name="$icon" class="h-4 w-4" />{{ __('ui.'.$label) }}</a>
                @endforeach
                <div class="mt-6 border-t border-token pt-4"><a href="{{ route('home') }}" class="nav-link"><x-icon name="external-link" class="h-4 w-4" />{{ __('ui.view_site') }}</a></div>
            </nav>
        </aside>
        <div class="min-w-0">
            <header class="surface flex min-h-19 items-center justify-between gap-4 border-b border-token px-5 sm:px-8"><p class="min-w-0 flex-1 truncate text-sm font-medium" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</p><div class="flex shrink-0 items-center gap-2"><x-theme-toggle /><x-notification-bell /><x-ui.button :href="route('profile.edit')" variant="ghost" size="sm">{{ __('ui.profile') }}</x-ui.button><form action="{{ route('logout') }}" method="POST">@csrf<x-ui.button type="submit" variant="ghost" class="button-icon" :aria-label="__('ui.logout')"><x-icon name="log-out" /></x-ui.button></form></div></header>
            @include('layouts.flash')
            <main id="main-content" tabindex="-1" class="admin-content">@yield('content')</main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
