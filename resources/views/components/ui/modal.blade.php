@props(['name', 'title'])
<div x-data="accessibleModal" @modal-open.window="if ($event.detail === @js($name)) show()" @keydown.escape.window="if(open) hide()" @keydown.tab="if(open) trap($event)">
    <div x-cloak x-show="open" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/50 p-4" @click.self="hide()">
        <section x-ref="dialog" role="dialog" aria-modal="true" aria-labelledby="modal-{{ $name }}-title" class="card w-full max-w-lg p-6" tabindex="-1">
            <div class="mb-5 flex items-center justify-between gap-4"><h2 id="modal-{{ $name }}-title" class="text-lg font-semibold">{{ $title }}</h2><x-ui.button variant="ghost" class="button-icon" @click="hide()" :aria-label="__('ui.close')"><x-icon name="x" /></x-ui.button></div>
            {{ $slot }}
        </section>
    </div>
</div>
