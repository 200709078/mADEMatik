@auth
@php($unreadCount = auth()->user()->unreadNotifications()->count())
<a href="{{ route('notifications.index') }}" {{ $attributes->class(['button button-ghost button-icon relative']) }} aria-label="{{ __('ui.notifications') }}@if($unreadCount) , {{ __('ui.unread', ['count' => $unreadCount]) }}@endif">
    <x-icon name="bell" />@if($unreadCount)<span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-[var(--brand)] px-1 text-[10px] text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>@endif
</a>
@endauth
