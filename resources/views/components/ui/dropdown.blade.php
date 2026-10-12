<div {{ $attributes->class(['relative']) }} x-data="{open:false}" @click.outside="open=false" @keydown.escape="open=false; $refs.trigger?.focus()">
    <button type="button" x-ref="trigger" @click="open=!open" :aria-expanded="open" class="button button-ghost">{{ $trigger ?? __('ui.actions') }}<x-icon name="chevron-down" class="h-4 w-4" /></button>
    <div x-cloak x-show="open" class="card absolute right-0 z-30 mt-2 min-w-48 p-2">{{ $slot }}</div>
</div>
