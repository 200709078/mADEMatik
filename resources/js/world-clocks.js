import * as SunCalc from 'suncalc';
import { DEFAULT_WORLD_CLOCK_IDS, MAX_WORLD_CLOCKS, WORLD_CLOCK_CITIES } from './world-clock-cities.js';

export const WORLD_CLOCK_STORAGE_KEY = 'madematik-world-clocks-v1';
export const DEFAULT_SOLAR_LOCATION = { latitude: 39.9334, longitude: 32.8597 };

const DAY_MS = 86400000;
const formatters = new Map();

function formatter(locale, timeZone, options) {
    const key = JSON.stringify([locale, timeZone, options]);
    if (!formatters.has(key)) formatters.set(key, new Intl.DateTimeFormat(locale, { timeZone, ...options }));
    return formatters.get(key);
}

function validLocale(value) {
    try { return Intl.DateTimeFormat.supportedLocalesOf([value])[0] || 'tr'; } catch (_) { return 'tr'; }
}

function validZone(value) {
    try { new Intl.DateTimeFormat('en', { timeZone: value }).format(0); return true; } catch (_) { return false; }
}

function deviceZone() {
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    return zone && validZone(zone) ? zone : 'UTC';
}

export function zonedDayNumber(now, timeZone) {
    const parts = formatter('en-GB', timeZone, { year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(now);
    const values = Object.fromEntries(parts.filter(part => part.type !== 'literal').map(part => [part.type, Number(part.value)]));
    return Date.UTC(values.year, values.month - 1, values.day) / DAY_MS;
}

export function utcOffsetMinutes(now, timeZone) {
    const parts = formatter('en-GB', timeZone, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).formatToParts(now);
    const values = Object.fromEntries(parts.filter(part => part.type !== 'literal').map(part => [part.type, Number(part.value)]));
    const localAsUtc = Date.UTC(values.year, values.month - 1, values.day, values.hour, values.minute, values.second);
    return Math.round((localAsUtc - Math.floor(Number(now) / 1000) * 1000) / 60000);
}

export function formatUtcOffset(minutes) {
    const absolute = Math.abs(minutes);
    return `UTC${minutes < 0 ? '−' : '+'}${String(Math.floor(absolute / 60)).padStart(2, '0')}:${String(absolute % 60).padStart(2, '0')}`;
}

function dayLabel(difference, labels) {
    return difference < 0 ? labels.previousDay || '' : difference > 0 ? labels.nextDay || '' : labels.sameDay || '';
}

function validCoordinates(latitude, longitude) {
    return Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
        && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180;
}

function validDate(value) {
    return value instanceof Date && Number.isFinite(value.getTime());
}

function durationText(minutes, labels) {
    return `${Math.floor(minutes / 60)} ${labels.hoursShort || 'sa'} ${minutes % 60} ${labels.minutesShort || 'dk'}`;
}

export function calculateSolarDay({ now, latitude, longitude, timeZone, locale = 'tr', labels = {} }, calculator = SunCalc) {
    if (!Number.isFinite(Number(now)) || !validCoordinates(latitude, longitude) || !validZone(timeZone)) throw new RangeError('Invalid solar input.');
    const times = calculator.getTimes(new Date(now), latitude, longitude, 0, utcOffsetMinutes(now, timeZone));
    const sunrise = validDate(times.sunrise) ? times.sunrise : null;
    const sunset = validDate(times.sunset) ? times.sunset : null;
    const state = times.alwaysUp ? 'polar-day' : times.alwaysDown ? 'polar-night' : sunrise && sunset ? 'normal' : 'no-event';
    const dateFormatter = formatter(locale, timeZone, { day: 'numeric', month: 'long', year: 'numeric' });
    const timeFormatter = formatter(locale, timeZone, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
    const today = zonedDayNumber(now, timeZone);
    const eventMinutes = sunrise && sunset ? Math.max(0, Math.min(1440, Math.round((sunset.getTime() - sunrise.getTime()) / 60000))) : null;
    return {
        state,
        date: dateFormatter.format(now),
        timeZone,
        offset: formatUtcOffset(utcOffsetMinutes(now, timeZone)),
        sunrise: sunrise ? timeFormatter.format(sunrise) : '—',
        sunset: sunset ? timeFormatter.format(sunset) : '—',
        sunriseDayLabel: sunrise ? dayLabel(zonedDayNumber(sunrise, timeZone) - today, labels) : '',
        sunsetDayLabel: sunset ? dayLabel(zonedDayNumber(sunset, timeZone) - today, labels) : '',
        daylight: state === 'no-event' ? '—' : durationText(state === 'polar-day' ? 1440 : state === 'polar-night' ? 0 : eventMinutes, labels),
        eventMessage: state === 'polar-day' ? labels.polarDay || '' : state === 'polar-night' ? labels.polarNight || '' : state === 'no-event' ? labels.solarNoEvent || '' : '',
    };
}

export function createWorldClockTool(labels = {}, environment = {}) {
    const locale = validLocale(labels.locale || globalThis.document?.documentElement?.lang || 'tr');
    const language = locale.split('-')[0];
    const cities = WORLD_CLOCK_CITIES.filter(city => validZone(city.timeZone)).map(city => ({ id: city.id, timeZone: city.timeZone, name: city.name[language] || city.name.en }));
    const byId = new Map(cities.map(city => [city.id, city]));
    const defaults = DEFAULT_WORLD_CLOCK_IDS.filter(id => byId.has(id));
    const now = environment.now || (() => Date.now());
    const browser = environment.window || globalThis.window;
    const document = environment.document || globalThis.document;
    const navigator = environment.navigator || globalThis.navigator;
    const getZone = environment.timeZone || deviceZone;
    let coordinates = null;
    let solarKey = '';
    let requestVersion = 0;
    let destroyed = false;
    let onVisibility;

    return {
        labels,
        worldNow: now(),
        selectedIds: [...defaults],
        selectedCityId: '',
        maxCities: MAX_WORLD_CLOCKS,
        interval: null,
        worldNotice: '',
        locationState: 'idle',
        locationError: '',
        locationDefault: false,
        solarData: null,

        init() {
            destroyed = false;
            try {
                const stored = (environment.storage || globalThis.localStorage).getItem(WORLD_CLOCK_STORAGE_KEY);
                if (stored !== null) {
                    let saved;
                    try { saved = stored.length <= 4096 ? JSON.parse(stored) : null; } catch (_) { saved = null; }
                    if (Array.isArray(saved)) this.selectedIds = [...new Set(saved.filter(id => typeof id === 'string' && byId.has(id)))].slice(0, MAX_WORLD_CLOCKS);
                    else this.persistCities();
                }
            } catch (_) { this.worldNotice = labels.storageUnavailable || ''; }
            this.chooseAvailableCity();
            this.tick();
            this.maybeAutoLocate();
            this.interval = browser.setInterval(() => this.tick(), 1000);
            onVisibility = () => this.tick();
            document.addEventListener('visibilitychange', onVisibility);
        },

        destroy() {
            destroyed = true;
            requestVersion++;
            coordinates = null;
            this.solarData = null;
            browser.clearInterval(this.interval);
            document.removeEventListener('visibilitychange', onVisibility);
        },

        tick() {
            this.worldNow = now();
            if (coordinates) this.refreshSolar();
        },

        get cityOptions() { return cities.filter(city => !this.selectedIds.includes(city.id)); },

        get selectedCities() {
            const today = zonedDayNumber(this.worldNow, getZone());
            return this.selectedIds.filter(id => byId.has(id)).map(id => {
                const city = byId.get(id);
                const difference = zonedDayNumber(this.worldNow, city.timeZone) - today;
                return {
                    ...city,
                    time: formatter(locale, city.timeZone, { hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).format(this.worldNow),
                    date: formatter(locale, city.timeZone, { weekday: 'short', day: 'numeric', month: 'short' }).format(this.worldNow),
                    offset: formatUtcOffset(utcOffsetMinutes(this.worldNow, city.timeZone)),
                    dayDifference: difference,
                    dayLabel: dayLabel(difference, labels),
                };
            });
        },

        chooseAvailableCity() {
            if (!this.cityOptions.some(city => city.id === this.selectedCityId)) this.selectedCityId = this.cityOptions[0]?.id || '';
        },

        persistCities() {
            try { (environment.storage || globalThis.localStorage).setItem(WORLD_CLOCK_STORAGE_KEY, JSON.stringify(this.selectedIds)); }
            catch (_) { this.worldNotice = labels.storageUnavailable || ''; }
        },

        addCity() {
            this.worldNotice = '';
            if (this.selectedIds.length >= MAX_WORLD_CLOCKS) { this.worldNotice = labels.cityLimitReached || ''; return; }
            if (!byId.has(this.selectedCityId)) { this.worldNotice = labels.invalidCity || ''; return; }
            if (this.selectedIds.includes(this.selectedCityId)) return;
            this.selectedIds.push(this.selectedCityId);
            this.persistCities();
            this.chooseAvailableCity();
        },

        removeCity(id) {
            this.worldNotice = '';
            this.selectedIds = this.selectedIds.filter(selected => selected !== id);
            this.persistCities();
            this.chooseAvailableCity();
        },

        resetCities() {
            this.worldNotice = '';
            this.selectedIds = [...defaults];
            this.persistCities();
            this.chooseAvailableCity();
        },

        get hasLocation() { return this.locationState === 'ready' && this.solarData !== null; },

        get locationMessage() {
            if (this.locationState === 'loading') return labels.locationLoading || '';
            if (this.locationState === 'ready') return this.locationDefault ? labels.locationDefault || '' : labels.locationReady || '';
            if (this.locationState === 'error') return this.locationError;
            return labels.locationIdle || '';
        },

        clearLocation() {
            requestVersion++;
            coordinates = null;
            solarKey = '';
            this.solarData = null;
            this.locationError = '';
            this.locationDefault = false;
            this.locationState = 'idle';
        },

        maybeAutoLocate() {
            try {
                const query = navigator?.permissions?.query;
                if (typeof query !== 'function') return;
                query.call(navigator.permissions, { name: 'geolocation' }).then(
                    status => {
                        if (destroyed) return;
                        if (status?.state === 'granted') this.requestLocation();
                        else this.useDefaultLocation();
                    },
                    () => { if (!destroyed) this.useDefaultLocation(); },
                );
            } catch (_) { /* konum yoksa güneş bölümü gizli kalır */ }
        },

        useDefaultLocation() {
            if (destroyed || coordinates || this.locationState === 'loading') return;
            coordinates = { ...DEFAULT_SOLAR_LOCATION };
            this.worldNow = now();
            this.locationDefault = true;
            this.locationState = 'ready';
            this.refreshSolar();
        },

        refreshSolar() {
            if (!coordinates) return;
            const timeZone = getZone();
            const key = `${zonedDayNumber(this.worldNow, timeZone)}:${timeZone}:${utcOffsetMinutes(this.worldNow, timeZone)}`;
            if (key === solarKey && this.solarData) return;
            try {
                this.solarData = calculateSolarDay({ now: this.worldNow, ...coordinates, timeZone, locale, labels }, environment.solarCalculator || SunCalc);
                solarKey = key;
            } catch (_) {
                coordinates = null;
                this.solarData = null;
                this.locationState = 'error';
                this.locationError = labels.solarUnavailable || '';
            }
        },

        requestLocation() {
            if (destroyed || this.locationState === 'loading') return;
            if (!browser.isSecureContext) { this.locationState = 'error'; this.locationError = labels.locationInsecure || ''; return; }
            if (!navigator?.geolocation?.getCurrentPosition) { this.locationState = 'error'; this.locationError = labels.locationUnsupported || ''; return; }
            const version = ++requestVersion;
            let settled = false;
            coordinates = null;
            solarKey = '';
            this.solarData = null;
            this.locationError = '';
            this.locationDefault = false;
            this.locationState = 'loading';
            const current = () => !destroyed && !settled && version === requestVersion;
            const fail = error => {
                if (!current()) return;
                settled = true;
                this.locationState = 'error';
                this.locationError = error?.code === 1 ? labels.locationDenied || '' : error?.code === 3 ? labels.locationTimeout || '' : labels.locationUnavailable || '';
            };
            try {
                navigator.geolocation.getCurrentPosition(position => {
                    if (!current()) return;
                    settled = true;
                    const latitude = position?.coords?.latitude;
                    const longitude = position?.coords?.longitude;
                    if (!validCoordinates(latitude, longitude)) { this.locationState = 'error'; this.locationError = labels.locationInvalid || ''; return; }
                    coordinates = { latitude, longitude };
                    this.worldNow = now();
                    this.locationState = 'ready';
                    this.refreshSolar();
                }, fail, { enableHighAccuracy: false, timeout: 12000, maximumAge: 300000 });
            } catch (_) { fail(); }
        },
    };
}
