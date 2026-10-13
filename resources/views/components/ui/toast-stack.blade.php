@props(['items' => []])
<div class="toast-stack" data-testid="toast-stack" role="region" aria-label="{{ __('ui.toast_region') }}" x-data="toastStack({{ Illuminate\Support\Js::from($items) }})" x-on:madematik-toast.window="push($event.detail)" x-cloak>
    <template x-for="toast in items" :key="toast.id">
        <div class="toast" data-testid="toast-card" :class="'toast-' + toast.type" :data-toast-id="toast.id" :role="toast.type === 'error' ? 'alert' : 'status'" aria-atomic="true" x-on:mouseenter="pause(toast.id, 'hover')" x-on:mouseleave="resume(toast.id, 'hover')" x-on:focusin="pause(toast.id, 'focus')" x-on:focusout="if (!$event.currentTarget.contains($event.relatedTarget)) resume(toast.id, 'focus')" x-on:keydown.escape.stop.prevent="dismiss(toast.id)">
            <span class="toast-icon" aria-hidden="true">
                <span x-show="toast.type === 'success'"><x-icon name="check-circle" /></span>
                <span x-show="toast.type === 'error' || toast.type === 'warning'"><x-icon name="alert-triangle" /></span>
                <span x-show="toast.type === 'info'"><x-icon name="info" /></span>
            </span>
            <div class="toast-content">
                <p class="toast-title" x-show="toast.title" x-text="toast.title"></p>
                <p class="toast-message" x-show="toast.messages.length === 1" x-text="toast.messages[0]"></p>
                <ul class="toast-messages" x-show="toast.messages.length > 1"><template x-for="(message, index) in toast.messages" :key="index"><li x-text="message"></li></template></ul>
            </div>
            <button type="button" class="toast-close" aria-label="{{ __('ui.toast_close') }}" x-on:click="dismiss(toast.id)"><x-icon name="x" class="h-4 w-4" /></button>
        </div>
    </template>
</div>
