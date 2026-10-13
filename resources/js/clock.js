export function createClockTool(labels = {}) {
    return {
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
    viewStorageKey: 'madematik-clock-view-v1',
    soundStorageKey: 'madematik-clock-sound-v1',
    soundOn: true,
    audioCtx: null,
    zoomLevels: { clock: 100, timer: 100 },
    viewNotice: '',
    presetMinutes: [5, 10, 15, 20, 40, 60],
    init() {
        try {
            let saved;
            try { saved = JSON.parse(localStorage.getItem(this.storageKey) || 'null'); } catch (_) { localStorage.removeItem(this.storageKey); }
            const validTimestamp = value => value === null || (Number.isFinite(value) && value >= 0);
            const validSaved = saved && Number.isInteger(saved.total) && saved.total > 0 && saved.total <= 359999000
                && Number.isFinite(saved.remaining) && saved.remaining >= 0 && saved.remaining <= saved.total
                && validTimestamp(saved.startedAt) && validTimestamp(saved.endsAt)
                && typeof saved.running === 'boolean' && typeof saved.completed === 'boolean'
                && (!saved.completed || (!saved.running && saved.remaining === 0))
                && (!saved.running || (!saved.completed && saved.startedAt !== null && saved.endsAt !== null && saved.endsAt >= saved.startedAt && saved.endsAt - saved.startedAt <= saved.total));
            if (validSaved) {
                this.total = saved.total;
                this.remaining = saved.remaining;
                this.startedAt = saved.startedAt;
                this.endsAt = saved.endsAt;
                this.running = saved.running;
                this.completed = saved.completed;
                this.mode = saved.mode === 'timer' ? 'timer' : 'clock';
                this.hours = Math.floor(this.total / 3600000);
                this.minutes = Math.floor(this.total / 60000) % 60;
                this.seconds = Math.floor(this.total / 1000) % 60;
            }
        } catch (_) { this.notice = this.labels.storageUnavailable; }
        try {
            const view = JSON.parse(localStorage.getItem(this.viewStorageKey) || 'null');
            for (const mode of ['clock', 'timer']) {
                if (Number.isInteger(view?.[mode]) && view[mode] >= 60 && view[mode] <= 400 && view[mode] % 10 === 0) this.zoomLevels[mode] = view[mode];
            }
        } catch (_) { this.viewNotice = this.labels.zoomStorageUnavailable || ''; }
        try {
            const sound = JSON.parse(localStorage.getItem(this.soundStorageKey) || 'null');
            if (typeof sound === 'boolean') this.soundOn = sound;
        } catch (_) { /* ses tercihi okunamazsa varsayılan (açık) korunur */ }
        this.tick();
        this.interval = window.setInterval(() => this.tick(), 200);
        this.onVisibility = () => this.tick();
        this.onFullscreen = () => { this.fullScreen = !!document.fullscreenElement; };
        this.onExpandedKeydown = event => {
            if (!this.expanded) return;
            if (event.key === 'Escape') { this.expanded = false; return; }
            if (event.key !== 'Tab') return;
            const elements = Array.from(this.$refs.panel.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled), [tabindex="0"]')).filter(el => el.offsetParent !== null && el.tabIndex >= 0);
            const first = elements[0], last = elements[elements.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        };
        document.addEventListener('visibilitychange', this.onVisibility);
        document.addEventListener('fullscreenchange', this.onFullscreen);
        document.addEventListener('keydown', this.onExpandedKeydown);
    },
    destroy() {
        window.clearInterval(this.interval);
        document.removeEventListener('visibilitychange', this.onVisibility);
        document.removeEventListener('fullscreenchange', this.onFullscreen);
        document.removeEventListener('keydown', this.onExpandedKeydown);
        document.body.style.overflow = '';
    },
    tick() {
        this.now = Date.now();
        if (this.running && this.endsAt) {
            this.remaining = Math.min(this.total, Math.max(0, this.endsAt - this.now));
            if (this.remaining === 0) {
                this.running = false;
                this.completed = true;
                this.persist();
                this.beep();
            }
        }
    },
    persist() {
        try { localStorage.setItem(this.storageKey, JSON.stringify({ total: this.total, remaining: this.remaining, startedAt: this.startedAt, endsAt: this.endsAt, running: this.running, completed: this.completed, mode: this.mode })); } catch (_) { this.notice = this.labels.storageUnavailable; }
    },
    setMode(mode) { this.mode = mode === 'timer' ? 'timer' : 'clock'; this.persist(); },
    get zoomPercent() { return this.zoomLevels[this.mode]; },
    get zoomScale() { return this.zoomPercent / 100; },
    changeZoom(change) {
        if (![10, -10, 0].includes(change)) return;
        this.zoomLevels[this.mode] = change === 0 ? 100 : Math.max(60, Math.min(400, this.zoomPercent + change));
        try { localStorage.setItem(this.viewStorageKey, JSON.stringify(this.zoomLevels)); this.viewNotice = ''; }
        catch (_) { this.viewNotice = this.labels.zoomStorageUnavailable || ''; }
    },
    selectPreset(minutes) {
        if (this.running || (this.startedAt !== null && !this.completed) || !this.presetMinutes.includes(minutes)) return;
        this.hours = Math.floor(minutes / 60);
        this.minutes = minutes % 60;
        this.seconds = 0;
        this.total = minutes * 60000;
        this.remaining = this.total;
        this.startedAt = null;
        this.endsAt = null;
        this.completed = false;
        this.notice = '';
        this.persist();
    },
    isPreset(minutes) { return Number(this.hours) * 60 + Number(this.minutes) === minutes && Number(this.seconds) === 0; },
    readDuration() {
        const durationPart = (value, maximum) => Math.trunc(Math.min(maximum, Math.max(0, Number(value) || 0)));
        return (durationPart(this.hours, 99) * 3600 + durationPart(this.minutes, 59) * 60 + durationPart(this.seconds, 59)) * 1000;
    },
    applyDuration() {
        if (this.running) return;
        const total = this.readDuration();
        if (!total) { this.notice = this.labels.invalidDuration; return; }
        this.total = total;
        this.remaining = this.total;
        this.startedAt = null;
        this.endsAt = null;
        this.completed = false;
        this.notice = '';
        this.persist();
    },
    start() {
        if (this.running) return;
        if (this.completed || this.startedAt === null) {
            const total = this.readDuration();
            if (!total) { this.notice = this.labels.invalidDuration; return; }
            this.total = total;
            this.remaining = this.total;
        }
        this.notice = '';
        this.startedAt = Date.now();
        this.endsAt = this.startedAt + this.remaining;
        this.running = true;
        this.completed = false;
        this.unlockAudio();
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
    get weekNumber() {
        const local = new Date(this.now);
        const day = new Date(Date.UTC(local.getFullYear(), local.getMonth(), local.getDate()));
        day.setUTCDate(day.getUTCDate() + 4 - (day.getUTCDay() || 7));
        return Math.ceil((((day - Date.UTC(day.getUTCFullYear(), 0, 1)) / 86400000) + 1) / 7);
    },
    get remainingPercent() { return Math.max(0, Math.min(100, this.total ? this.remaining / this.total * 100 : 0)); },
    get countdownText() {
        const value = Math.ceil(this.remaining / 1000);
        const parts = [Math.floor(value / 60) % 60, value % 60].map(part => String(part).padStart(2, '0'));
        if (this.total >= 3600000) parts.unshift(String(Math.floor(value / 3600)).padStart(2, '0'));
        return parts.join(':');
    },
    toggleSound() {
        this.soundOn = !this.soundOn;
        try { localStorage.setItem(this.soundStorageKey, JSON.stringify(this.soundOn)); }
        catch (_) { this.notice = this.labels.storageUnavailable; }
        if (this.soundOn) this.unlockAudio();
    },
    unlockAudio() {
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            if (!this.audioCtx) this.audioCtx = new Ctx();
            if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
        } catch (_) { /* ses desteklenmiyorsa sessiz devam edilir */ }
    },
    beep() {
        try {
            if (!this.soundOn) return;
            this.unlockAudio();
            const ctx = this.audioCtx;
            if (!ctx || ctx.state !== 'running') return;
            [0, 0.3].forEach(delay => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 660;
                gain.gain.setValueAtTime(0.0001, ctx.currentTime + delay);
                gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + delay + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + delay + 0.2);
                osc.connect(gain).connect(ctx.destination);
                osc.start(ctx.currentTime + delay);
                osc.stop(ctx.currentTime + delay + 0.22);
            });
        } catch (_) { /* ses hatası sayacı etkilemez */ }
    },
    async toggleFullscreen() {
        try {
            if (this.expanded) this.expanded = false;
            else if (document.fullscreenElement) await document.exitFullscreen();
            else if (this.$refs.panel.requestFullscreen) await this.$refs.panel.requestFullscreen();
            else this.expanded = true;
        } catch (_) { this.expanded = true; }
    },
};
}
