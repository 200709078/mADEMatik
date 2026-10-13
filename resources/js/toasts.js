export function createToastStack(initial = [], refresh = () => {}) {
    const timers = new Map();
    let nextId = 0;

    return {
        items: [],
        init() {
            if (Array.isArray(initial)) initial.forEach(item => this.push(item));
        },
        push(detail) {
            if (!detail || typeof detail !== 'object') return;
            const type = ['success', 'error', 'warning', 'info'].includes(detail.type) ? detail.type : 'info';
            const messages = (Array.isArray(detail.messages) ? detail.messages : [detail.message])
                .filter(message => typeof message === 'string' && message.trim()).map(message => message.trim());
            if (!messages.length) return;
            const title = typeof detail.title === 'string' ? detail.title : '';
            const key = JSON.stringify([type, title, messages]);
            if (this.items.some(item => item.key === key)) return;
            const duration = type === 'error' ? 0 : type === 'warning' ? 8000 : 6000;
            const toast = { id: ++nextId, key, type, title, messages, duration, remaining: duration, startedAt: 0, paused: new Set() };
            this.items.push(toast);
            this.arm(toast);
            this.$nextTick(refresh);
        },
        arm(toast) {
            if (!toast.duration || toast.paused.size || timers.has(toast.id)) return;
            toast.startedAt = Date.now();
            timers.set(toast.id, setTimeout(() => this.dismiss(toast.id), toast.remaining));
        },
        pause(id, reason) {
            const toast = this.items.find(item => item.id === id);
            if (!toast || toast.paused.has(reason)) return;
            if (timers.has(id)) {
                toast.remaining = Math.max(0, toast.remaining - (Date.now() - toast.startedAt));
                clearTimeout(timers.get(id));
                timers.delete(id);
            }
            toast.paused.add(reason);
        },
        resume(id, reason) {
            const toast = this.items.find(item => item.id === id);
            if (!toast) return;
            toast.paused.delete(reason);
            this.arm(toast);
        },
        dismiss(id) {
            const element = this.$root?.querySelector(`[data-toast-id="${id}"]`);
            const restoreFocus = element?.contains(document.activeElement);
            clearTimeout(timers.get(id));
            timers.delete(id);
            this.items = this.items.filter(item => item.id !== id);
            if (restoreFocus) this.$nextTick(() => document.getElementById('main-content')?.focus({ preventScroll: true }));
        },
        destroy() {
            timers.forEach(timer => clearTimeout(timer));
            timers.clear();
        },
    };
}
