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

Alpine.data('clockTool', (labels = {}) => ({
    labels,
    mode: 'clock',
    now: Date.now(),
    hours: 0,
    minutes: 5,
    seconds: 0,
    total: 300000,
    remaining: 300000,
    startedAt: null,
    endsAt: null,
    running: false,
    completed: false,
    fullScreen: false,
    expanded: false,
    interval: null,
    notice: '',
    storageKey: 'madematik-clock-v1',
    init() {
        try {
            const saved = JSON.parse(localStorage.getItem(this.storageKey) || 'null');
            if (saved && Number.isFinite(saved.total) && saved.total > 0 && saved.total <= 359999000) {
                this.total = saved.total;
                this.remaining = Math.max(0, Math.min(Number(saved.remaining) || 0, this.total));
                this.startedAt = Number.isFinite(saved.startedAt) ? saved.startedAt : null;
                this.endsAt = Number.isFinite(saved.endsAt) ? saved.endsAt : null;
                this.running = !!saved.running && this.endsAt !== null;
                this.completed = !!saved.completed;
                this.mode = saved.mode === 'timer' ? 'timer' : 'clock';
                this.hours = Math.floor(this.total / 3600000);
                this.minutes = Math.floor(this.total / 60000) % 60;
                this.seconds = Math.floor(this.total / 1000) % 60;
            }
        } catch (_) { this.notice = this.labels.storageUnavailable; }
        this.tick();
        this.interval = window.setInterval(() => this.tick(), 200);
        this.onVisibility = () => this.tick();
        this.onFullscreen = () => { this.fullScreen = !!document.fullscreenElement; };
        document.addEventListener('visibilitychange', this.onVisibility);
        document.addEventListener('fullscreenchange', this.onFullscreen);
    },
    destroy() {
        window.clearInterval(this.interval);
        document.removeEventListener('visibilitychange', this.onVisibility);
        document.removeEventListener('fullscreenchange', this.onFullscreen);
        document.body.style.overflow = '';
    },
    tick() {
        this.now = Date.now();
        if (this.running && this.endsAt) {
            this.remaining = Math.max(0, this.endsAt - this.now);
            if (this.remaining === 0) {
                this.running = false;
                this.completed = true;
                this.persist();
            }
        }
    },
    persist() {
        try { localStorage.setItem(this.storageKey, JSON.stringify({ total: this.total, remaining: this.remaining, startedAt: this.startedAt, endsAt: this.endsAt, running: this.running, completed: this.completed, mode: this.mode })); } catch (_) {}
    },
    setMode(mode) { this.mode = mode; this.persist(); },
    start() {
        if (this.running) return;
        if (this.completed || this.startedAt === null) {
            this.total = (Math.min(99, Math.max(0, Number(this.hours) || 0)) * 3600 + Math.min(59, Math.max(0, Number(this.minutes) || 0)) * 60 + Math.min(59, Math.max(0, Number(this.seconds) || 0))) * 1000;
            if (!this.total) { this.notice = this.labels.invalidDuration; return; }
            this.remaining = this.total;
        }
        this.notice = '';
        this.startedAt = Date.now();
        this.endsAt = this.startedAt + this.remaining;
        this.running = true;
        this.completed = false;
        this.persist();
        this.tick();
    },
    pause() { this.tick(); this.running = false; this.endsAt = null; this.persist(); },
    reset() {
        this.running = false;
        this.completed = false;
        this.startedAt = null;
        this.endsAt = null;
        this.remaining = this.total;
        this.notice = '';
        this.persist();
    },
    get clockText() { return new Intl.DateTimeFormat(document.documentElement.lang || 'tr', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(this.now); },
    get dateText() { return new Intl.DateTimeFormat(document.documentElement.lang || 'tr', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(this.now); },
    get countdownText() {
        const value = Math.ceil(this.remaining / 1000);
        return [Math.floor(value / 3600), Math.floor(value / 60) % 60, value % 60].map(part => String(part).padStart(2, '0')).join(':');
    },
    async toggleFullscreen() {
        try {
            if (this.expanded) this.expanded = false;
            else if (document.fullscreenElement) await document.exitFullscreen();
            else if (this.$refs.panel.requestFullscreen) await this.$refs.panel.requestFullscreen();
            else this.expanded = true;
        } catch (_) { this.expanded = true; }
    },
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
