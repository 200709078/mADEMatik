import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createClockTool } from '../../resources/js/clock.js';

const key = 'madematik-clock-v1';
const labels = { storageUnavailable: 'Depolama kullanılamıyor.', invalidDuration: 'Geçersiz süre.' };

function environment(initial = null) {
    const records = new Map(initial === null ? [] : [[key, typeof initial === 'string' ? initial : JSON.stringify(initial)]]);
    const listeners = new Map();
    const timers = new Map();
    let now = 1800000000000;
    let nextTimer = 0;
    const originalNow = Date.now;
    Date.now = () => now;
    globalThis.localStorage = {
        getItem: name => records.get(name) ?? null,
        setItem: (name, value) => records.set(name, value),
        removeItem: name => records.delete(name),
    };
    globalThis.window = {
        setInterval: callback => { const id = ++nextTimer; timers.set(id, callback); return id; },
        clearInterval: id => timers.delete(id),
    };
    globalThis.document = {
        documentElement: { lang: 'tr' }, body: { style: { overflow: '' } },
        addEventListener: (name, callback) => { if (!listeners.has(name)) listeners.set(name, new Set()); listeners.get(name).add(callback); },
        removeEventListener: (name, callback) => listeners.get(name)?.delete(callback),
    };
    return {
        records, listeners, timers,
        advance: milliseconds => { now += milliseconds; },
        saved: () => JSON.parse(records.get(key)),
        restore: () => { Date.now = originalNow; delete globalThis.localStorage; delete globalThis.window; delete globalThis.document; },
    };
}

test('çalışan sayaç sekme yeniden açıldığında saklanan bitiş zamanını kullanır', () => {
    const env = environment();
    try {
        const timer = createClockTool(labels); timer.init(); timer.minutes = 2; timer.start();
        assert.equal(env.saved().endsAt - env.saved().startedAt, 120000);
        env.advance(70000); timer.destroy();
        const reopened = createClockTool(labels); reopened.init();
        assert.equal(reopened.countdownText, '00:50'); assert.equal(reopened.running, true);
        reopened.destroy();
    } finally { env.restore(); }
});

test('duraklama boyunca süre azalmaz; devam edildiğinde kalan süre tamamlanır', () => {
    const env = environment();
    try {
        const timer = createClockTool(labels); timer.init(); timer.minutes = 1; timer.start();
        env.advance(20000); timer.pause();
        assert.equal(timer.remaining, 40000); assert.equal(env.saved().endsAt, null);
        env.advance(100000); timer.destroy();
        const reopened = createClockTool(labels); reopened.init();
        assert.equal(reopened.countdownText, '00:40'); reopened.start();
        env.advance(40000); reopened.tick();
        assert.equal(reopened.countdownText, '00:00'); assert.equal(reopened.completed, true); assert.equal(reopened.running, false);
        assert.equal(env.saved().completed, true); reopened.destroy();
    } finally { env.restore(); }
});

test('kapalı sekmede biten sayaç yeniden açıldığında tamamlanmış görünür', () => {
    const env = environment();
    try {
        const timer = createClockTool(labels); timer.init(); timer.minutes = 0; timer.seconds = 5; timer.start(); timer.destroy();
        env.advance(10000);
        const reopened = createClockTool(labels); reopened.init();
        assert.equal(reopened.completed, true); assert.equal(reopened.remaining, 0); assert.equal(env.saved().running, false); reopened.destroy();
    } finally { env.restore(); }
});

test('bozuk veya tutarsız tarayıcı kayıtları güvenli varsayılan süreye döner', () => {
    const snapshots = ['{invalid', { total: 300000, remaining: -1, running: true }, { total: 300000, remaining: 20000, startedAt: 1, endsAt: 900000000, running: true, completed: false }, { total: '300000', remaining: 300000, startedAt: null, endsAt: null, running: false, completed: false }];
    for (const snapshot of snapshots) {
        const env = environment(snapshot);
        try {
            const timer = createClockTool(labels); timer.init();
            assert.equal(timer.total, 300000); assert.equal(timer.remaining, 300000); assert.equal(timer.running, false); timer.destroy();
        } finally { env.restore(); }
    }
});

test('sıfır süre başlatılmaz ve mevcut süre bozulmaz', () => {
    const env = environment();
    try {
        const timer = createClockTool(labels); timer.init(); timer.hours = 0; timer.minutes = 0; timer.seconds = 0; timer.start();
        assert.equal(timer.running, false); assert.equal(timer.total, 300000); assert.equal(timer.notice, labels.invalidDuration); timer.destroy();
    } finally { env.restore(); }
});

test('süre alanları tamsayı ve izin verilen sınırlarla normalize edilir', () => {
    const env = environment();
    try {
        const timer = createClockTool(labels); timer.init(); timer.hours = -2; timer.minutes = 1.8; timer.seconds = 400; timer.start();
        assert.equal(timer.total, 119000); timer.destroy();
    } finally { env.restore(); }
});

test('depolama kapalı olduğunda sayaç çalışır ve kalıcılık sınırını bildirir', () => {
    const env = environment();
    try {
        globalThis.localStorage.getItem = () => { throw new Error('blocked'); };
        globalThis.localStorage.setItem = () => { throw new Error('blocked'); };
        const timer = createClockTool(labels); timer.init(); timer.start();
        assert.equal(timer.running, true); assert.equal(timer.notice, labels.storageUnavailable); timer.destroy();
    } finally { env.restore(); }
});

test('sıfırlama başlangıç/bitiş zamanını kaldırır ve dinleyiciler kapanır', () => {
    const env = environment();
    try {
        const timer = createClockTool(labels); timer.init(); timer.start(); env.advance(10000); timer.reset();
        assert.equal(env.saved().startedAt, null); assert.equal(env.saved().endsAt, null); assert.equal(timer.remaining, timer.total);
        timer.destroy(); assert.equal(env.timers.size, 0); assert.equal([...env.listeners.values()].reduce((count, entries) => count + entries.size, 0), 0);
    } finally { env.restore(); }
});

test('sistem saati geriye alınsa da gösterilen süre başlangıç süresini aşmaz', () => {
    const env = environment();
    try {
        const timer = createClockTool(labels); timer.init(); timer.start(); env.advance(-60000); timer.tick();
        assert.equal(timer.remaining, timer.total); timer.destroy();
    } finally { env.restore(); }
});

test('büyütülmüş sayaç odağı etkin sekme ve son kontrol arasında dolaşır', () => {
    const env = environment();
    try {
        const control = tabIndex => ({ tabIndex, offsetParent: {}, focus() { document.activeElement = this; } });
        const inactiveTab = control(-1), activeTab = control(0), lastButton = control(0);
        const timer = createClockTool(labels);
        timer.$refs = { panel: { querySelectorAll: () => [inactiveTab, activeTab, lastButton] } };
        timer.init(); timer.expanded = true;
        document.activeElement = lastButton;
        let prevented = 0;
        timer.onExpandedKeydown({ key: 'Tab', shiftKey: false, preventDefault: () => prevented++ });
        assert.equal(document.activeElement, activeTab);
        timer.onExpandedKeydown({ key: 'Tab', shiftKey: true, preventDefault: () => prevented++ });
        assert.equal(document.activeElement, lastButton);
        assert.equal(prevented, 2); timer.destroy();
    } finally { env.restore(); }
});

test('süre biçimi bir saatten kısaysa MM:SS, uzunsa HH:MM:SS olur; hazır süreler ve ses tercihi çalışır', () => {
    const env = environment();
    try {
        const timer = createClockTool(labels); timer.init();
        assert.deepEqual(timer.presetMinutes, [5, 10, 15, 20, 40, 60]);
        timer.selectPreset(5);
        assert.equal(timer.countdownText, '05:00');
        timer.hours = 1; timer.minutes = 2; timer.seconds = 3; timer.applyDuration();
        assert.equal(timer.total, 3723000); assert.equal(timer.countdownText, '01:02:03');
        assert.equal(timer.soundOn, true);
        timer.toggleSound();
        assert.equal(timer.soundOn, false); assert.equal(JSON.parse(env.records.get('madematik-clock-sound-v1')), false);
        timer.toggleSound();
        assert.equal(timer.soundOn, true);
        for (let i = 0; i < 40; i++) timer.changeZoom(10);
        assert.equal(timer.zoomPercent, 400);
        timer.destroy();
    } finally { env.restore(); }
});
