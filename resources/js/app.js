import { createClockTool } from './clock';
import Alpine from 'alpinejs';
import { createIcons, ArrowRight, ArrowLeft, ChevronLeft, ChevronRight, ChevronDown, Menu, X, Sun, Moon, Monitor, Bell, Check, CheckCircle, AlertTriangle, Info, Clock, Timer, Play, Pause, RotateCcw, Maximize, Minimize, Mail, Send, Code2, GraduationCap, Layers, ExternalLink, Search, Filter, User, Users, LogOut, LayoutDashboard, Folder, MessageSquare, Settings, ShieldCheck, MoreHorizontal, Trash2, Edit3, Plus, Image, Eye, EyeOff, FileText, Home, LoaderCircle, Inbox, CircleHelp, Flag, Globe, Heart, Activity } from 'lucide';

const iconSet = { ArrowRight, ArrowLeft, ChevronLeft, ChevronRight, ChevronDown, Menu, X, Sun, Moon, Monitor, Bell, Check, CheckCircle, AlertTriangle, Info, Clock, Timer, Play, Pause, RotateCcw, Maximize, Minimize, Mail, Send, Code2, GraduationCap, Layers, ExternalLink, Search, Filter, User, Users, LogOut, LayoutDashboard, Folder, MessageSquare, Settings, ShieldCheck, MoreHorizontal, Trash2, Edit3, Plus, Image, Eye, EyeOff, FileText, Home, LoaderCircle, Inbox, CircleHelp, Flag, Globe, Heart, Activity };
const refreshIcons = () => createIcons({ icons: iconSet, attrs: { 'stroke-width': 1.7 } });

Alpine.store('theme', {
    mode: 'system',
    dark: false,
    init() {
        try { this.mode = localStorage.getItem('madematik-theme') || 'system'; } catch (_) {}
        this.apply();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (this.mode === 'system') this.apply(); });
    },
    apply() {
        this.dark = this.mode === 'dark' || (this.mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.dataset.theme = this.dark ? 'dark' : 'light';
        document.querySelectorAll('[data-brand-logo]').forEach(logo => {
            const source = this.dark ? logo.dataset.darkSrc : logo.dataset.lightSrc;
            if (logo.getAttribute('src') !== source) logo.src = source;
        });
    },
    toggle() {
        this.mode = this.dark ? 'light' : 'dark';
        try { localStorage.setItem('madematik-theme', this.mode); } catch (_) {}
        this.apply();
    },
    system() {
        this.mode = 'system';
        try { localStorage.removeItem('madematik-theme'); } catch (_) {}
        this.apply();
    },
});

Alpine.data('heroSlider', (count = 3) => ({
    active: 0,
    count,
    next() { this.active = (this.active + 1) % this.count; },
    previous() { this.active = (this.active - 1 + this.count) % this.count; },
}));

Alpine.data('clockTool', createClockTool);

Alpine.data('profilePhoto', () => ({
    previewUrl: null,
    hasFile: false,
    selectPhoto(event) {
        this.clearPreview();
        const file = event.target.files?.[0];
        this.hasFile = !!file;
        if (file && file.size <= 2 * 1024 * 1024 && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            this.previewUrl = URL.createObjectURL(file);
        }
    },
    clearPreview() {
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },
    destroy() { this.clearPreview(); },
}));

Alpine.data('accessibleModal', () => ({
    open: false,
    previousFocus: null,
    show() { this.previousFocus = document.activeElement; this.open = true; this.$nextTick(() => this.$refs.dialog?.querySelector('button, a, input, textarea, select, [tabindex="0"]')?.focus()); },
    hide() { this.open = false; this.previousFocus?.focus(); },
    trap(event) {
        const elements = Array.from(this.$refs.dialog?.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled), textarea:not(:disabled), select:not(:disabled), [tabindex="0"]') || []).filter(el => el.offsetParent !== null);
        if (!elements.length) return;
        const first = elements[0], last = elements[elements.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    },
}));

window.Alpine = Alpine;
document.addEventListener('alpine:initialized', refreshIcons);
Alpine.start();
refreshIcons();
