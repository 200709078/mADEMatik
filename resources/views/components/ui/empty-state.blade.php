@props(['title' => null, 'description' => null, 'icon' => 'inbox'])
<div {{ $attributes->class(['card p-8 sm:p-12 text-center']) }}>
    <span class="surface-muted mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-xl"><x-icon :name="$icon" class="h-6 w-6 text-muted" /></span>
    <h3 class="text-lg font-semibold">{{ $title ?? __('ui.empty') }}</h3>
    @if($description)<p class="text-muted mx-auto mt-2 max-w-lg text-sm leading-7">{{ $description }}</p>@endif
    @if(trim((string) $slot))<div class="mt-5">{{ $slot }}</div>@endif
</div>
