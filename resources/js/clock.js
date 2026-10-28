const MODES = ['clock', 'timer', 'stopwatch'];
// Kronometre en fazla 100 saat ve 99 tur tutar; bozuk kayıtlar bu sınırlarla elenir.
const STOPWATCH_LIMIT = 360000000;
export const MAX_LAPS = 99;
const DURATION_LIMIT = 359999000;
// Bitiş sesi: üç kez üçlü bip (yaklaşık 2,5 sn).
const ALARM_BEEPS = [0, .18, .36, .9, 1.08, 1.26, 1.8, 1.98, 2.16];
const WAKE_LOCK_RETRY = 10000;

const pad = part => String(part).padStart(2, '0');
// Saniye cinsinden süre: bir saatten kısaysa MM:SS, uzunsa HH:MM:SS.
function durationText(seconds) {
    const parts = [Math.floor(seconds / 60) % 60, seconds % 60].map(pad);
    if (seconds >= 3600) parts.unshift(pad(Math.floor(seconds / 3600)));
    return parts.join(':');
}

// 0,1 saniyelik çözünürlük: bir saatten kısaysa MM:SS.d, uzunsa HH:MM:SS.d.
export function stopwatchFormat(milliseconds) {
    const tenths = Math.floor(Math.max(0, milliseconds) / 100);
    const seconds = Math.floor(tenths / 10);
    return `${durationText(seconds)}.${tenths % 10}`;
}

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
    stopwatchElapsed: 0,
    stopwatchStartedAt: null,
    laps: [],
    maxLaps: MAX_LAPS,
    storageKey: 'madematik-clock-v1',
    stopwatchStorageKey: 'madematik-stopwatch-v1',
    viewStorageKey: 'madematik-clock-view-v1',
    soundStorageKey: 'madematik-clock-sound-v1',
    soundOn: true,
    audioCtx: null,
    // Tarayıcı sesi ancak bir dokunuştan sonra çalar; yenilenen sayfada bu bilgi uyarı olarak gösterilir.
    audioReady: false,
    alarmNodes: [],
    alarmAt: null,
    wakeLock: null,
    wakeLockPending: false,
    wakeLockRetryAt: 0,
    baseTitle: null,
    zoomLevels: { clock: 100, timer: 100, stopwatch: 100 },
    zoomAtLimit: false,
    viewNotice: '',
    presetMinutes: [5, 10, 15, 20, 30, 40, 45, 60],
    init() {
        try {
            let saved;
            try { saved = JSON.parse(localStorage.getItem(this.storageKey) || 'null'); } catch (_) { localStorage.removeItem(this.storageKey); }
            const validTimestamp = value => value === null || (Number.isFinite(value) && value >= 0);
            const validSaved = saved && Number.isInteger(saved.total) && saved.total > 0 && saved.total <= DURATION_LIMIT
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
                this.mode = MODES.includes(saved.mode) ? saved.mode : 'clock';
                this.hours = Math.floor(this.total / 3600000);
                this.minutes = Math.floor(this.total / 60000) % 60;
                this.seconds = Math.floor(this.total / 1000) % 60;
            }
        } catch (_) { this.notice = this.labels.storageUnavailable; }
        try {
            const saved = JSON.parse(localStorage.getItem(this.stopwatchStorageKey) || 'null');
            const validTime = value => Number.isFinite(value) && value >= 0 && value <= STOPWATCH_LIMIT;
            const validLaps = Array.isArray(saved?.laps) && saved.laps.length <= MAX_LAPS
                && saved.laps.every((lap, index) => validTime(lap) && (index === 0 || lap >= saved.laps[index - 1]));
            if (saved && validTime(saved.elapsed) && (saved.startedAt === null || (Number.isFinite(saved.startedAt) && saved.startedAt >= 0)) && validLaps) {
                this.stopwatchElapsed = saved.elapsed;
                this.stopwatchStartedAt = saved.startedAt;
                this.laps = saved.laps;
            }
        } catch (_) { this.notice = this.labels.storageUnavailable; }
        try {
            const view = JSON.parse(localStorage.getItem(this.viewStorageKey) || 'null');
            for (const mode of MODES) {
                if (Number.isInteger(view?.[mode]) && view[mode] >= 60 && view[mode] <= 400 && view[mode] % 10 === 0) this.zoomLevels[mode] = view[mode];
            }
        } catch (_) { this.viewNotice = this.labels.zoomStorageUnavailable || ''; }
        try {
            const sound = JSON.parse(localStorage.getItem(this.soundStorageKey) || 'null');
            if (typeof sound === 'boolean') this.soundOn = sound;
        } catch (_) { /* ses tercihi okunamazsa varsayılan (açık) korunur */ }
        if (typeof document.title === 'string') this.baseTitle = document.title;
        this.tick();
        // Kronometre onda bir saniye gösterir.
        this.interval = window.setInterval(() => this.tick(), 100);
        this.onVisibility = () => this.tick();
        this.onFullscreen = () => { this.fullScreen = !!document.fullscreenElement; this.scheduleZoomMeasurement(); };
        this.onExpandedKeydown = event => {
            if (!this.expanded) return;
            if (event.key === 'Escape') { this.expanded = false; return; }
            if (event.key !== 'Tab') return;
            const elements = Array.from(this.$refs.panel.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled), [tabindex="0"]')).filter(el => el.offsetParent !== null && el.tabIndex >= 0);
            const first = elements[0], last = elements[elements.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        };
        this.onShortcut = event => this.shortcut(event);
        // Yenilenen sayfada ses ilk dokunuşta açılır ve bitiş anına zamanlanır.
        this.onGesture = () => { if (this.running && this.soundOn && !this.audioReady) { this.unlockAudio(); this.scheduleAlarm(); } };
        document.addEventListener('keydown', this.onShortcut);
        document.addEventListener('pointerdown', this.onGesture);
        document.addEventListener('visibilitychange', this.onVisibility);
        document.addEventListener('fullscreenchange', this.onFullscreen);
        document.addEventListener('keydown', this.onExpandedKeydown);
        this.onResize = () => this.scheduleZoomMeasurement();
        window.addEventListener?.('resize', this.onResize);
        if (typeof this.$watch === 'function') this.$watch('expanded', this.onResize);
        this.$nextTick?.(() => {
            if (typeof ResizeObserver === 'function' && this.$refs?.panel) {
                this.zoomResizeObserver = new ResizeObserver(this.onResize);
                for (const element of [this.$refs.panel, this.$refs.clockFace, this.$refs.timerFace]) {
                    if (element) this.zoomResizeObserver.observe(element);
                }
            }
            this.updateZoomLimit();
        });
    },
    destroy() {
        window.clearInterval(this.interval);
        document.removeEventListener('keydown', this.onShortcut);
        document.removeEventListener('pointerdown', this.onGesture);
        document.removeEventListener('visibilitychange', this.onVisibility);
        document.removeEventListener('fullscreenchange', this.onFullscreen);
        this.cancelAlarm();
        this.wakeLock?.release?.().catch?.(() => {});
        this.wakeLock = null;
        if (this.baseTitle !== null) document.title = this.baseTitle;
        document.removeEventListener('keydown', this.onExpandedKeydown);
        window.removeEventListener?.('resize', this.onResize);
        this.zoomResizeObserver?.disconnect();
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
                // Zamanlanmış ses çaldıysa yinelenmez; uyku vb. yüzünden gecikmişse şimdi çalınır.
                const played = this.alarmAt !== null && this.audioCtx?.currentTime >= this.alarmAt - .05;
                if (played) { this.alarmNodes = []; this.alarmAt = null; }
                else { this.cancelAlarm(); this.beep(); }
            }
        }
        this.updateTitle();
        this.syncWakeLock();
    },
    updateTitle() {
        if (this.baseTitle === null) return;
        const prefix = this.completed ? this.labels.timeUp
            : this.running ? this.countdownText
            : this.stopwatchRunning ? this.stopwatchText.slice(0, -2) : '';
        const title = prefix ? `${prefix} · ${this.baseTitle}` : this.baseTitle;
        if (document.title !== title) document.title = title;
    },
    // Sayaç ya da kronometre çalışırken ve tam ekranda ekran kararmaz (desteklenen tarayıcılarda).
    async syncWakeLock() {
        const wanted = (this.running || this.stopwatchRunning || this.fullScreen || this.expanded) && document.visibilityState !== 'hidden';
        if (!wanted) {
            const lock = this.wakeLock;
            this.wakeLock = null;
            if (lock) { try { await lock.release(); } catch (_) { /* zaten bırakılmış */ } }
            return;
        }
        const api = globalThis.navigator?.wakeLock;
        if (this.wakeLock || this.wakeLockPending || typeof api?.request !== 'function' || Date.now() < this.wakeLockRetryAt) return;
        this.wakeLockPending = true;
        try {
            const lock = await api.request('screen');
            this.wakeLock = lock;
            lock.addEventListener?.('release', () => { if (this.wakeLock === lock) this.wakeLock = null; });
        } catch (_) { this.wakeLockRetryAt = Date.now() + WAKE_LOCK_RETRY; }
        finally { this.wakeLockPending = false; }
    },
    shortcut(event) {
        if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey || event.repeat) return;
        if (event.target?.closest?.('input, textarea, select, button, a, [contenteditable="true"]')) return;
        const key = String(event.key).toLocaleLowerCase('tr');
        const act = action => { event.preventDefault(); action(); };
        if (key === 'f') return act(() => this.toggleFullscreen());
        if (this.mode === 'timer') {
            if (key === ' ') return act(() => (this.running ? this.pause() : this.start()));
            if (key === 'r') return act(() => this.reset());
        } else if (this.mode === 'stopwatch') {
            if (key === ' ') return act(() => (this.stopwatchRunning ? this.pauseStopwatch() : this.startStopwatch()));
            if (key === 'r') return act(() => this.resetStopwatch());
            if (key === 't') return act(() => this.lap());
        }
    },
    persist() {
        try { localStorage.setItem(this.storageKey, JSON.stringify({ total: this.total, remaining: this.remaining, startedAt: this.startedAt, endsAt: this.endsAt, running: this.running, completed: this.completed, mode: this.mode })); } catch (_) { this.notice = this.labels.storageUnavailable; }
    },
    setMode(mode) { this.mode = MODES.includes(mode) ? mode : 'clock'; this.zoomAtLimit = false; this.persist(); this.scheduleZoomMeasurement(); },
    // Sekmeler arasında sağ/sol okla dolaşılır; odak yeni sekmeye geçer.
    cycleMode(step) {
        this.setMode(MODES[(MODES.indexOf(this.mode) + step + MODES.length) % MODES.length]);
        this.$refs?.[`${this.mode}Tab`]?.focus();
    },
    get zoomPercent() { return this.zoomLevels[this.mode]; },
    get zoomScale() { return this.zoomPercent / 100; },
    get canZoomIn() { return this.zoomPercent < 400 && !this.zoomAtLimit; },
    scheduleZoomMeasurement() { this.$nextTick?.(() => this.updateZoomLimit()); },
    updateZoomLimit() {
        const face = this.$refs?.[`${this.mode}Face`];
        const limit = this.$refs?.zoomLimit;
        if (!face?.isConnected || !limit?.isConnected || typeof window.getComputedStyle !== 'function') return;
        const currentSize = Number.parseFloat(window.getComputedStyle(face).fontSize);
        const maximumSize = Number.parseFloat(window.getComputedStyle(limit).fontSize);
        // The hidden reference uses the same CSS at maximum zoom, including its width cap.
        this.zoomAtLimit = Number.isFinite(currentSize) && Number.isFinite(maximumSize)
            && maximumSize > 0 && currentSize >= maximumSize - 0.1;
    },
    changeZoom(change) {
        if (![10, -10, 0].includes(change)) return;
        if (change === 10 && !this.canZoomIn) return;
        this.zoomLevels[this.mode] = change === 0 ? 100 : Math.max(60, Math.min(400, this.zoomPercent + change));
        try { localStorage.setItem(this.viewStorageKey, JSON.stringify(this.zoomLevels)); this.viewNotice = ''; }
        catch (_) { this.viewNotice = this.labels.zoomStorageUnavailable || ''; }
        this.scheduleZoomMeasurement();
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
        this.scheduleAlarm();
        this.persist();
        this.tick();
    },
    pause() { this.tick(); this.running = false; this.endsAt = null; this.cancelAlarm(); this.persist(); },
    reset() {
        this.cancelAlarm();
        this.running = false;
        this.completed = false;
        this.startedAt = null;
        this.endsAt = null;
        this.remaining = this.total;
        this.notice = '';
        this.persist();
    },
    get stopwatchRunning() { return this.stopwatchStartedAt !== null; },
    get stopwatchTotal() {
        const running = this.stopwatchRunning ? Math.max(0, this.now - this.stopwatchStartedAt) : 0;
        return Math.min(STOPWATCH_LIMIT, this.stopwatchElapsed + running);
    },
    get stopwatchText() { return stopwatchFormat(this.stopwatchTotal); },
    // En yeni tur üstte: sıra, o ana kadarki toplam ve önceki turdan bu yana geçen süre.
    get lapRows() {
        return this.laps.map((total, index) => ({ number: index + 1, total: stopwatchFormat(total), split: stopwatchFormat(total - (this.laps[index - 1] ?? 0)) })).reverse();
    },
    persistStopwatch() {
        try { localStorage.setItem(this.stopwatchStorageKey, JSON.stringify({ elapsed: this.stopwatchElapsed, startedAt: this.stopwatchStartedAt, laps: this.laps })); }
        catch (_) { this.notice = this.labels.storageUnavailable; }
    },
    startStopwatch() {
        if (this.stopwatchRunning || this.stopwatchElapsed >= STOPWATCH_LIMIT) return;
        this.now = Date.now();
        this.stopwatchStartedAt = this.now;
        this.persistStopwatch();
    },
    pauseStopwatch() {
        if (!this.stopwatchRunning) return;
        this.now = Date.now();
        this.stopwatchElapsed = this.stopwatchTotal;
        this.stopwatchStartedAt = null;
        this.persistStopwatch();
    },
    lap() {
        if (!this.stopwatchRunning || this.laps.length >= MAX_LAPS) return;
        this.now = Date.now();
        this.laps = [...this.laps, Math.max(this.stopwatchTotal, this.laps.at(-1) ?? 0)];
        this.persistStopwatch();
    },
    resetStopwatch() {
        this.stopwatchElapsed = 0;
        this.stopwatchStartedAt = null;
        this.laps = [];
        this.persistStopwatch();
    },
    get clockText() { return new Intl.DateTimeFormat(document.documentElement.lang || 'tr', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(this.now); },
    get dateText() { return new Intl.DateTimeFormat(document.documentElement.lang || 'tr', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(this.now); },
    get weekNumber() {
        const local = new Date(this.now);
        const day = new Date(Date.UTC(local.getFullYear(), local.getMonth(), local.getDate()));
        day.setUTCDate(day.getUTCDate() + 4 - (day.getUTCDay() || 7));
        return Math.ceil((((day - Date.UTC(day.getUTCFullYear(), 0, 1)) / 86400000) + 1) / 7);
    },
    // Süre bitince ne kadar aşıldığı: −MM:SS.
    get overtimeText() {
        const over = this.completed && this.endsAt !== null ? Math.min(DURATION_LIMIT, Math.max(0, this.now - this.endsAt)) : 0;
        const seconds = Math.floor(over / 1000);
        return `${seconds > 0 ? '−' : ''}${durationText(seconds)}`;
    },
    get timerFaceText() { return this.completed ? this.overtimeText : this.countdownText; },
    get faceText() { return this.mode === 'timer' ? this.timerFaceText : this.mode === 'stopwatch' ? this.stopwatchText : this.clockText; },
    // Tam ekranda rakamlar bu genişliğe (em) göre ekrana sığdırılır: rakam ve eksi ~0,6 em, ayraçlar ~0,3 em.
    get faceEm() {
        let em = .2;
        for (const character of this.faceText) em += /[0-9−]/.test(character) ? .6 : .3;
        return Math.round(em * 10) / 10;
    },
    get lastMinute() { return this.running && this.remaining <= 60000; },
    get remainingPercent() { return Math.max(0, Math.min(100, this.total ? this.remaining / this.total * 100 : 0)); },
    get countdownText() {
        const value = Math.ceil(this.remaining / 1000);
        const parts = [Math.floor(value / 60) % 60, value % 60].map(pad);
        if (this.total >= 3600000) parts.unshift(pad(Math.floor(value / 3600)));
        return parts.join(':');
    },
    toggleSound() {
        this.soundOn = !this.soundOn;
        try { localStorage.setItem(this.soundStorageKey, JSON.stringify(this.soundOn)); }
        catch (_) { this.notice = this.labels.storageUnavailable; }
        if (this.soundOn) { this.unlockAudio(); this.scheduleAlarm(); } else this.cancelAlarm();
    },
    unlockAudio() {
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            if (!this.audioCtx) this.audioCtx = new Ctx();
            const ctx = this.audioCtx;
            this.audioReady = ctx.state === 'running';
            if (ctx.state === 'suspended') {
                Promise.resolve(ctx.resume()).then(() => {
                    this.audioReady = ctx.state === 'running';
                    this.scheduleAlarm();
                }, () => {});
            }
        } catch (_) { /* ses desteklenmiyorsa sessiz devam edilir */ }
    },
    playAlarm(ctx, at) {
        return ALARM_BEEPS.map(offset => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            const start = at + offset;
            osc.type = 'triangle';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.45, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.14);
            osc.connect(gain).connect(ctx.destination);
            osc.start(start);
            osc.stop(start + 0.16);
            return osc;
        });
    },
    // Ses, sayaç başlarken bitiş anına zamanlanır: arka plandaki sekmede zamanlayıcı yavaşlasa da zamanında çalar.
    scheduleAlarm() {
        this.cancelAlarm();
        const ctx = this.audioCtx;
        if (!this.soundOn || !this.running || this.endsAt === null || ctx?.state !== 'running') return;
        try {
            this.alarmAt = ctx.currentTime + Math.max(0, (this.endsAt - Date.now()) / 1000);
            this.alarmNodes = this.playAlarm(ctx, this.alarmAt);
        } catch (_) { this.alarmNodes = []; this.alarmAt = null; }
    },
    cancelAlarm() {
        for (const node of this.alarmNodes) { try { node.stop(); node.disconnect?.(); } catch (_) { /* çalmış ya da durmuş */ } }
        this.alarmNodes = [];
        this.alarmAt = null;
    },
    beep() {
        try {
            if (!this.soundOn) return;
            this.unlockAudio();
            const ctx = this.audioCtx;
            if (!ctx || ctx.state !== 'running') return;
            this.playAlarm(ctx, ctx.currentTime);
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
