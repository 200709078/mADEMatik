<button type="button" {{ $attributes->class(['button button-ghost button-icon']) }} @click="$store.theme.toggle()" :aria-label="$store.theme.dark ? @js(__('ui.light_theme')) : @js(__('ui.dark_theme'))" :title="$store.theme.dark ? @js(__('ui.light_theme')) : @js(__('ui.dark_theme'))">
    <span x-show="!$store.theme.dark"><x-icon name="moon" /></span><span x-cloak x-show="$store.theme.dark"><x-icon name="sun" /></span>
</button>
