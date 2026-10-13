import assert from 'node:assert/strict';
import { test } from 'node:test';
import * as SunCalc from 'suncalc';
import { createWorldClockTool, calculateSolarDay, utcOffsetMinutes, formatUtcOffset, WORLD_CLOCK_STORAGE_KEY } from '../../resources/js/world-clocks.js';
import { DEFAULT_WORLD_CLOCK_IDS, MAX_WORLD_CLOCKS, WORLD_CLOCK_CITIES } from '../../resources/js/world-clock-cities.js';

const labels = {
    locale: 'tr', storageUnavailable: 'Depolama kullanılamıyor.', cityLimitReached: 'En fazla 8 şehir.', invalidCity: 'Geçersiz şehir.',
    sameDay: 'Bugün', previousDay: 'Önceki gün', nextDay: 'Sonraki gün',
    locationIdle: 'Konumunuzu kullanın.', locationLoading: 'Konum alınıyor.', locationReady: 'Konum hazır.',
    locationDenied: 'İzin verilmedi.', locationUnavailable: 'Konum alınamadı.', locationTimeout: 'Zaman aşımı.',
    locationUnsupported: 'Konum desteklenmiyor.', locationInsecure: 'Güvenli bağlantı gerekli.', locationInvalid: 'Geçersiz konum.',
    solarUnavailable: 'Hesaplanamadı.', polarDay: 'Güneş batmıyor.', polarNight: 'Güneş doğmuyor.', solarNoEvent: 'Olay yok.',
    hoursShort: 'sa', minutesShort: 'dk',
};

function environment({ saved = null, time = '2026-10-13T12:00:00Z', zone = 'Europe/Istanbul' } = {}) {
    let now = Date.parse(time);
    let sequence = 0;
    const records = new Map(saved === null ? [] : [[WORLD_CLOCK_STORAGE_KEY, typeof saved === 'string' ? saved : JSON.stringify(saved)]]);
    const timers = new Map();
    const listeners = new Map();
    const requests = [];
    const dependencies = {
        now: () => now,
        timeZone: () => zone,
        storage: {
            getItem: key => records.get(key) ?? null,
            setItem: (key, value) => records.set(key, value),
        },
        window: {
            isSecureContext: true,
            setInterval: callback => { const id = ++sequence; timers.set(id, callback); return id; },
            clearInterval: id => timers.delete(id),
        },
        document: {
            documentElement: { lang: 'tr' },
            addEventListener: (name, callback) => listeners.set(name, callback),
            removeEventListener: (name, callback) => { if (listeners.get(name) === callback) listeners.delete(name); },
        },
        navigator: { geolocation: { getCurrentPosition: (success, error, options) => requests.push({ success, error, options }) } },
    };
    return {
        dependencies, records, timers, listeners, requests,
        setTime: value => { now = Date.parse(value); },
        tool: () => { const tool = createWorldClockTool(labels, dependencies); tool.init(); return tool; },
        saved: () => JSON.parse(records.get(WORLD_CLOCK_STORAGE_KEY)),
    };
}

const location = (latitude = 41.0082, longitude = 28.9784) => ({ coords: { latitude, longitude } });
const flushPermissions = () => new Promise(resolve => setImmediate(resolve));

function withPermission(env, state) {
    const status = { state, onchange: null };
    env.dependencies.navigator.permissions = { query: async descriptor => {
        assert.deepEqual(descriptor, { name: 'geolocation' });
        return status;
    } };
    return status;
}

function changePermission(status, state) {
    status.state = state;
    assert.equal(typeof status.onchange, 'function');
    status.onchange({ target: status });
}

test('şehir kataloğu benzersiz kimlikler, Türkçe/İngilizce adlar ve geçerli IANA bölgeleri içerir', () => {
    assert.equal(new Set(WORLD_CLOCK_CITIES.map(city => city.id)).size, WORLD_CLOCK_CITIES.length);
    for (const city of WORLD_CLOCK_CITIES) {
        assert.ok(city.name.tr && city.name.en);
        assert.doesNotThrow(() => new Intl.DateTimeFormat('tr', { timeZone: city.timeZone }).format(0));
    }
});

test('varsayılan dünya saatleri Ankara, İstanbul, Londra, New York, Tokyo ve Sidney sırasıyla aynı anı gösterir', () => {
    const env = environment(); const tool = env.tool();
    assert.deepEqual(tool.selectedIds, ['ankara', 'istanbul', 'london', 'new-york', 'tokyo', 'sydney']);
    assert.deepEqual(tool.selectedCities.map(city => city.time), ['15:00:00', '15:00:00', '13:00:00', '08:00:00', '21:00:00', '23:00:00']);
    assert.equal(tool.selectedCities[0].name, 'Ankara');
    assert.equal(tool.selectedCities[1].name, 'İstanbul');
    assert.equal(tool.selectedCities[0].offset, 'UTC+03:00');
    tool.destroy();
});

test('IANA dünya saatleri Amerikan yaz saati değişiminde atlanan saati ve yeni UTC farkını gösterir', () => {
    const env = environment({ time: '2026-03-08T06:30:00Z' }); const tool = env.tool();
    const before = tool.selectedCities.find(city => city.id === 'new-york');
    assert.equal(before.time, '01:30:00'); assert.equal(before.offset, 'UTC−05:00');
    env.setTime('2026-03-08T07:30:00Z'); tool.tick();
    const after = tool.selectedCities.find(city => city.id === 'new-york');
    assert.equal(after.time, '03:30:00'); assert.equal(after.offset, 'UTC−04:00');
    tool.destroy();
});

test('Avrupa ve güney yarımküre yaz saati, yarım ve çeyrek saat farkları doğru hesaplanır', () => {
    assert.equal(utcOffsetMinutes(Date.parse('2026-01-15T12:00:00Z'), 'Europe/London'), 0);
    assert.equal(utcOffsetMinutes(Date.parse('2026-07-15T12:00:00Z'), 'Europe/London'), 60);
    assert.equal(utcOffsetMinutes(Date.parse('2026-01-15T12:00:00Z'), 'Australia/Sydney'), 660);
    assert.equal(utcOffsetMinutes(Date.parse('2026-07-15T12:00:00Z'), 'Australia/Sydney'), 600);
    assert.equal(formatUtcOffset(utcOffsetMinutes(Date.parse('2026-07-15T12:00:00Z'), 'Asia/Kolkata')), 'UTC+05:30');
    assert.equal(formatUtcOffset(utcOffsetMinutes(Date.parse('2026-07-15T12:00:00Z'), 'Asia/Kathmandu')), 'UTC+05:45');
});

test('şehir kartları cihaz takvimine göre önceki ve sonraki günü ayırır', () => {
    const env = environment({ time: '2026-10-13T21:30:00Z' }); const tool = env.tool();
    assert.equal(tool.selectedCities.find(city => city.id === 'istanbul').dayLabel, labels.sameDay);
    assert.equal(tool.selectedCities.find(city => city.id === 'new-york').dayLabel, labels.previousDay);
    tool.selectedIds = ['tokyo', 'honolulu'];
    env.dependencies.timeZone = () => 'Pacific/Honolulu';
    const alternate = createWorldClockTool(labels, env.dependencies); alternate.init();
    assert.equal(alternate.selectedCities.find(city => city.id === 'tokyo').dayLabel, labels.nextDay);
    alternate.destroy(); tool.destroy();
});

test('şehir ekleme/kaldırma/sıfırlama yalnız favori kimliklerini saklar ve yeniden açıldığında korur', () => {
    const env = environment(); const tool = env.tool();
    tool.selectedCityId = 'kathmandu'; tool.addCity(); tool.removeCity('london');
    assert.deepEqual(env.saved(), [...DEFAULT_WORLD_CLOCK_IDS.filter(id => id !== 'london'), 'kathmandu']);
    assert.equal(tool.cityOptions.some(city => city.id === 'kathmandu'), false);
    tool.destroy();
    const reopened = env.tool(); assert.deepEqual(reopened.selectedIds, env.saved());
    reopened.resetCities(); assert.deepEqual(env.saved(), DEFAULT_WORLD_CLOCK_IDS); reopened.destroy();
});

test('boş favori seçimi korunur; kullanıcı yeniden şehir ekleyebilir', () => {
    const env = environment({ saved: [] }); const tool = env.tool();
    assert.deepEqual(tool.selectedCities, []); assert.equal(tool.cityOptions.length, WORLD_CLOCK_CITIES.length);
    tool.addCity(); assert.equal(tool.selectedCities.length, 1); tool.destroy();
});

test('hatalı, fazla, tekrarlanan ve bilinmeyen favoriler sınırlı beyaz listeye alınır', () => {
    const ids = WORLD_CLOCK_CITIES.slice(0, 12).map(city => city.id);
    const env = environment({ saved: ['__proto__', null, { id: 'istanbul' }, 'istanbul', 'istanbul', ...ids] }); const tool = env.tool();
    assert.equal(tool.selectedIds.length, MAX_WORLD_CLOCKS);
    assert.equal(new Set(tool.selectedIds).size, MAX_WORLD_CLOCKS);
    assert.equal(tool.selectedIds.includes('__proto__'), false);
    tool.selectedCityId = 'honolulu'; tool.addCity();
    assert.equal(tool.selectedIds.length, MAX_WORLD_CLOCKS); assert.equal(tool.worldNotice, labels.cityLimitReached);
    tool.destroy();
    for (const saved of ['{broken', JSON.stringify({ city: 'istanbul' }), ' '.repeat(5000)]) {
        const corrupted = environment({ saved }); const restored = corrupted.tool();
        assert.deepEqual(restored.selectedIds, DEFAULT_WORLD_CLOCK_IDS); assert.deepEqual(corrupted.saved(), DEFAULT_WORLD_CLOCK_IDS); restored.destroy();
    }
});

test('bilinmeyen seçim reddedilir; tekrar aynı şehir eklenmez', () => {
    const env = environment(); const tool = env.tool();
    tool.selectedCityId = '<script>'; tool.addCity(); assert.equal(tool.worldNotice, labels.invalidCity);
    tool.selectedCityId = 'istanbul'; tool.addCity(); assert.equal(tool.selectedIds.length, DEFAULT_WORLD_CLOCK_IDS.length);
    tool.destroy();
});

test('tarayıcı depolaması engelli olsa da dünya saatleri ve favori işlemleri çalışır', () => {
    const env = environment();
    env.dependencies.storage.getItem = () => { throw new Error('blocked'); };
    env.dependencies.storage.setItem = () => { throw new Error('blocked'); };
    const tool = env.tool(); assert.equal(tool.worldNotice, labels.storageUnavailable);
    tool.selectedCityId = 'paris'; tool.addCity(); assert.equal(tool.selectedCities.length, DEFAULT_WORLD_CLOCK_IDS.length + 1);
    assert.equal(tool.worldNotice, labels.storageUnavailable); tool.destroy();
});

test('konum istek yardımcısı geçerli konumdan güneş saatlerini cihaz diliminde hesaplar', () => {
    const env = environment(); const tool = env.tool();
    assert.equal(env.requests.length, 0); assert.equal(tool.locationState, 'idle');
    tool.requestLocation(); assert.equal(env.requests.length, 1); assert.equal(tool.locationMessage, labels.locationLoading);
    assert.deepEqual(env.requests[0].options, { enableHighAccuracy: false, timeout: 12000, maximumAge: 300000 });
    env.requests[0].success(location());
    assert.equal(tool.locationState, 'ready'); assert.equal(tool.hasLocation, true);
    assert.equal(tool.solarData.timeZone, 'Europe/Istanbul'); assert.equal(tool.solarData.offset, 'UTC+03:00');
    assert.equal(tool.solarData.state, 'normal'); assert.match(tool.solarData.sunrise, /^0[67]:\d{2}$/);
    assert.match(tool.solarData.sunset, /^18:\d{2}$/); assert.match(tool.solarData.daylight, /^11 sa \d+ dk$/);
    assert.equal(env.records.size, 0); tool.destroy();
});

test('konum izni verilmemiş veya reddedilmişse konum istenmez ve güneş bilgileri gösterilmez', async () => {
    for (const state of ['prompt', 'denied']) {
        const env = environment(); withPermission(env, state);
        const tool = env.tool(); await flushPermissions();
        assert.equal(env.requests.length, 0);
        assert.equal(tool.locationState, 'idle'); assert.equal(tool.hasLocation, false); assert.equal(tool.solarData, null);
        tool.destroy();
    }
});

test('konum izni verilmişse açılışta gerçek konum otomatik kullanılır', async () => {
    const env = environment(); const status = withPermission(env, 'granted');
    const tool = env.tool(); await flushPermissions();
    assert.equal(env.requests.length, 1);
    assert.equal(tool.hasLocation, false); assert.equal(tool.solarData, null);
    env.requests[0].success(location());
    assert.equal(tool.locationState, 'ready'); assert.equal(tool.hasLocation, true);
    assert.equal(tool.locationMessage, labels.locationReady);
    assert.equal(tool.solarData.timeZone, 'Europe/Istanbul');
    assert.equal(typeof status.onchange, 'function');
    tool.destroy(); assert.equal(status.onchange, null);
});

test('sekme açıkken konum izni verilmesi gerçek konumu otomatik alır', async () => {
    for (const initialState of ['prompt', 'denied']) {
        const env = environment(); const status = withPermission(env, initialState);
        const tool = env.tool(); await flushPermissions();
        assert.equal(env.requests.length, 0); assert.equal(tool.hasLocation, false);
        changePermission(status, 'granted'); assert.equal(env.requests.length, 1);
        env.requests[0].success(location()); assert.equal(tool.hasLocation, true); assert.equal(tool.locationState, 'ready');
        tool.destroy(); assert.equal(status.onchange, null);
    }
});

test('izin sonradan kaldırılırsa hesaplanan güneş bilgileri ve konum bellekten temizlenir', async () => {
    for (const nextState of ['prompt', 'denied']) {
        const env = environment(); const status = withPermission(env, 'granted');
        const tool = env.tool(); await flushPermissions(); env.requests[0].success(location());
        assert.equal(tool.hasLocation, true);
        changePermission(status, nextState);
        assert.equal(tool.hasLocation, false); assert.equal(tool.solarData, null); assert.equal(tool.locationState, 'idle');
        tool.tick(); assert.equal(tool.solarData, null); assert.equal(env.requests.length, 1);
        tool.destroy();
    }
});

test('konum alınırken izin kaldırılması gecikmiş konum yanıtını geçersiz kılar', async () => {
    for (const nextState of ['prompt', 'denied']) {
        const env = environment(); const status = withPermission(env, 'granted');
        const tool = env.tool(); await flushPermissions(); assert.equal(tool.locationState, 'loading');
        changePermission(status, nextState);
        env.requests[0].success(location()); env.requests[0].error({ code: 1 });
        assert.equal(tool.hasLocation, false); assert.equal(tool.solarData, null); assert.equal(tool.locationState, 'idle');
        changePermission(status, 'granted'); assert.equal(env.requests.length, 2);
        env.requests[1].success(location()); assert.equal(tool.hasLocation, true);
        tool.destroy();
    }
});

test('sayfadan ayrılınca izin dinleyicisi ve bekleyen konum isteği kaldırılır', async () => {
    const env = environment(); const status = withPermission(env, 'granted');
    const tool = env.tool(); await flushPermissions();
    const oldChange = status.onchange;
    tool.destroy(); assert.equal(status.onchange, null);
    env.requests[0].success(location());
    status.state = 'granted'; oldChange({ target: status });
    assert.equal(env.requests.length, 1); assert.equal(tool.solarData, null); assert.equal(tool.hasLocation, false);
    assert.equal(env.timers.size, 0); assert.equal(env.listeners.size, 0);
});

test('sayfadan ayrıldıktan sonra tamamlanan izin sorgusu konum isteği veya dinleyici oluşturmaz', async () => {
    const env = environment(); let resolveQuery;
    env.dependencies.navigator.permissions = { query: () => new Promise(resolve => { resolveQuery = resolve; }) };
    const tool = env.tool(); tool.destroy();
    const status = { state: 'granted', onchange: null };
    resolveQuery(status); await flushPermissions();
    assert.equal(env.requests.length, 0); assert.equal(status.onchange, null); assert.equal(tool.solarData, null);
});

test('Permissions API yoksa veya izin sorgusu hata verirse güneş bölümü gizli kalır', async () => {
    for (const permissions of [undefined, {}, { query: () => { throw new Error('unsupported'); } }, { query: async () => { throw new Error('unsupported'); } }]) {
        const env = environment(); env.dependencies.navigator.permissions = permissions;
        const tool = env.tool(); await flushPermissions();
        assert.equal(env.requests.length, 0); assert.equal(tool.locationState, 'idle'); assert.equal(tool.hasLocation, false); assert.equal(tool.solarData, null);
        tool.destroy();
    }
});

test('otomatik izin kontrolü güvensiz bağlantıda veya konum API yokken konum isteği üretmez', async () => {
    const insecure = environment(); withPermission(insecure, 'granted'); insecure.dependencies.window.isSecureContext = false;
    const first = insecure.tool(); await flushPermissions();
    assert.equal(insecure.requests.length, 0); assert.equal(first.hasLocation, false); assert.equal(first.solarData, null); first.destroy();
    const unsupported = environment(); withPermission(unsupported, 'granted'); delete unsupported.dependencies.navigator.geolocation;
    const second = unsupported.tool(); await flushPermissions();
    assert.equal(unsupported.requests.length, 0); assert.equal(second.hasLocation, false); assert.equal(second.solarData, null); second.destroy();
});

test('izin verilmiş olsa da konum alma başarısızsa varsayılan konum veya güneş bilgisi gösterilmez', async () => {
    for (const code of [1, 2, 3]) {
        const env = environment(); withPermission(env, 'granted');
        const tool = env.tool(); await flushPermissions(); env.requests[0].error({ code });
        assert.equal(tool.hasLocation, false); assert.equal(tool.solarData, null); assert.equal(env.requests.length, 1);
        tool.destroy();
    }
});

test('konum ve koordinatlar favoriler dahil tarayıcı depolamasına yazılmaz', () => {
    const env = environment(); const tool = env.tool(); tool.requestLocation(); env.requests[0].success(location());
    tool.selectedCityId = 'paris'; tool.addCity();
    assert.deepEqual([...env.records.keys()], [WORLD_CLOCK_STORAGE_KEY]);
    assert.deepEqual(env.saved(), [...DEFAULT_WORLD_CLOCK_IDS, 'paris']);
    assert.doesNotMatch([...env.records.values()].join(''), /latitude|longitude|41\.0082|28\.9784/);
    tool.clearLocation(); assert.equal(tool.hasLocation, false); assert.equal(tool.solarData, null); assert.equal(tool.locationState, 'idle'); tool.destroy();
});

test('konum izin reddi, zaman aşımı ve cihaz hatası farklı mesajlarla yeniden denenebilir', () => {
    for (const [code, expected] of [[1, labels.locationDenied], [2, labels.locationUnavailable], [3, labels.locationTimeout]]) {
        const env = environment(); const tool = env.tool(); tool.requestLocation(); env.requests[0].error({ code });
        assert.equal(tool.locationState, 'error'); assert.equal(tool.locationMessage, expected); assert.equal(tool.solarData, null);
        tool.requestLocation(); env.requests[1].success(location()); assert.equal(tool.locationState, 'ready'); tool.destroy();
    }
});

test('güvensiz bağlantı, desteklenmeyen API ve eşzamanlı tarayıcı hatası güvenle bildirilir', () => {
    const insecure = environment(); insecure.dependencies.window.isSecureContext = false;
    const first = insecure.tool(); first.requestLocation(); assert.equal(first.locationMessage, labels.locationInsecure); assert.equal(insecure.requests.length, 0); first.destroy();
    const unsupported = environment(); unsupported.dependencies.navigator = {};
    const second = unsupported.tool(); second.requestLocation(); assert.equal(second.locationMessage, labels.locationUnsupported); second.destroy();
    const broken = environment(); broken.dependencies.navigator.geolocation.getCurrentPosition = () => { throw new Error('unavailable'); };
    const third = broken.tool(); third.requestLocation(); assert.equal(third.locationMessage, labels.locationUnavailable); third.destroy();
});

test('geçersiz veya sınır dışı koordinatlar hesaplanmaz', () => {
    for (const [latitude, longitude] of [[91, 0], [0, 181], [NaN, 0], [0, Infinity], ['41', 29]]) {
        const env = environment(); const tool = env.tool(); tool.requestLocation(); env.requests[0].success(location(latitude, longitude));
        assert.equal(tool.locationState, 'error'); assert.equal(tool.locationMessage, labels.locationInvalid); assert.equal(tool.solarData, null); tool.destroy();
    }
});

test('konum unutma ve sayfadan ayrılma eski izin yanıtlarını geçersiz kılar', () => {
    const env = environment(); const tool = env.tool(); tool.requestLocation(); tool.clearLocation();
    env.requests[0].success(location()); assert.equal(tool.locationState, 'idle'); assert.equal(tool.solarData, null);
    tool.requestLocation(); tool.destroy(); env.requests[1].success(location());
    assert.equal(tool.solarData, null); assert.equal(tool.hasLocation, false); assert.equal(env.timers.size, 0); assert.equal(env.listeners.size, 0);
});

test('yeniden denemeden sonra eski ve çift geolocation yanıtları yeni sonucu değiştirmez', () => {
    const env = environment(); const tool = env.tool(); tool.requestLocation();
    env.requests[0].error({ code: 3 }); tool.requestLocation(); env.requests[1].success(location());
    const data = tool.solarData;
    env.requests[0].success(location(0, 0)); env.requests[1].error({ code: 1 });
    assert.equal(tool.locationState, 'ready'); assert.equal(tool.solarData, data); tool.destroy();
});

test('yerel gece yarısı ve görünürlük değişimi güneş verisini yeni güne taşır; her saniye yeniden hesaplamaz', () => {
    const env = environment({ time: '2026-10-12T20:59:59Z' }); let calculations = 0;
    env.dependencies.solarCalculator = { getTimes: (...args) => { calculations++; return SunCalc.getTimes(...args); } };
    const tool = env.tool(); tool.requestLocation(); env.requests[0].success(location());
    assert.equal(tool.solarData.date, '12 Ekim 2026'); tool.tick(); assert.equal(calculations, 1);
    env.setTime('2026-10-12T21:00:01Z'); env.listeners.get('visibilitychange')();
    assert.equal(tool.solarData.date, '13 Ekim 2026'); assert.equal(calculations, 2); tool.destroy();
});

test('aynı takvim günündeki yaz saati geçişi güneş gününün UTC farkını günceller', () => {
    const env = environment({ time: '2026-03-08T06:30:00Z', zone: 'America/New_York' }); const tool = env.tool();
    tool.requestLocation(); env.requests[0].success(location(40.7128, -74.006)); assert.equal(tool.solarData.offset, 'UTC−05:00');
    env.setTime('2026-03-08T07:30:00Z'); tool.tick(); assert.equal(tool.solarData.offset, 'UTC−04:00'); tool.destroy();
});

test('yüksek enlemde kutup gündüzü/gecesi geçersiz saat yerine açıklama ve doğru gün uzunluğu gösterir', () => {
    for (const [now, state, daylight, message] of [
        ['2026-06-21T12:00:00Z', 'polar-day', '24 sa 0 dk', labels.polarDay],
        ['2026-12-21T12:00:00Z', 'polar-night', '0 sa 0 dk', labels.polarNight],
    ]) {
        const data = calculateSolarDay({ now: Date.parse(now), latitude: 69.6492, longitude: 18.9553, timeZone: 'Europe/Oslo', labels });
        assert.equal(data.state, state); assert.equal(data.sunrise, '—'); assert.equal(data.sunset, '—'); assert.equal(data.daylight, daylight); assert.equal(data.eventMessage, message);
    }
});

test('güneş hesaplama hatası ve bulunmayan olay kullanıcının diğer saatlerini bozmaz', () => {
    const env = environment(); env.dependencies.solarCalculator = { getTimes: () => { throw new Error('failed'); } };
    const tool = env.tool(); tool.requestLocation(); env.requests[0].success(location());
    assert.equal(tool.locationMessage, labels.solarUnavailable); assert.equal(tool.solarData, null); assert.equal(tool.selectedCities.length, DEFAULT_WORLD_CLOCK_IDS.length); tool.destroy();
    const data = calculateSolarDay({ now: Date.parse('2026-10-13T12:00:00Z'), latitude: 41, longitude: 29, timeZone: 'Europe/Istanbul', labels }, { getTimes: () => ({ sunrise: new Date(NaN), sunset: null }) });
    assert.equal(data.state, 'no-event'); assert.equal(data.daylight, '—'); assert.equal(data.eventMessage, labels.solarNoEvent);
});

test('konum başka bir saat dilimindeyse güneş olayının takvim günü farkı gösterilir', () => {
    const data = calculateSolarDay({ now: Date.parse('2026-10-13T12:00:00Z'), latitude: 35.6762, longitude: 139.6503, timeZone: 'America/New_York', labels });
    assert.equal(data.timeZone, 'America/New_York');
    assert.equal(data.sunriseDayLabel, labels.sameDay);
    assert.equal(data.sunsetDayLabel, labels.nextDay);
});

test('güneş girdileri hatalı zaman dilimi ve koordinat için sessiz yanlış sonuç üretmez', () => {
    assert.throws(() => calculateSolarDay({ now: NaN, latitude: 41, longitude: 29, timeZone: 'Europe/Istanbul' }), RangeError);
    assert.throws(() => calculateSolarDay({ now: Date.now(), latitude: 91, longitude: 29, timeZone: 'Europe/Istanbul' }), RangeError);
    assert.throws(() => calculateSolarDay({ now: Date.now(), latitude: 41, longitude: 29, timeZone: 'invalid/zone' }), RangeError);
});
