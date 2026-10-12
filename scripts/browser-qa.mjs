import assert from 'node:assert/strict';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const baseURL = process.env.QA_BASE_URL || 'http://127.0.0.1:8015';
const origin = new URL(baseURL).origin;
if (!['127.0.0.1', 'localhost'].includes(new URL(baseURL).hostname) || new URL(baseURL).protocol !== 'http:') {
    throw new Error('Tarayıcı kontrolleri yalnız izole yerel HTTP sunucusunda çalışabilir.');
}
const output = path.resolve('storage/runtime/qa');
await mkdir(output, { recursive: true });
const browserTemp = path.resolve('.local/t');
await mkdir(browserTemp, { recursive: true });
process.env.TMPDIR = browserTemp;
const browser = await chromium.launch({ executablePath: '/usr/bin/chromium', headless: true, args: ['--no-sandbox', '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1, EXCLUDE localhost'] });
const report = { baseURL, startedAt: new Date().toISOString(), checks: [], blockedExternalRequests: [], screenshots: [] };
const seenBlocked = new Set();
let fixtures = null;
if (process.env.QA_FIXTURE_PATH) fixtures = JSON.parse(await readFile(process.env.QA_FIXTURE_PATH, 'utf8'));
const scopes = new Set((process.env.QA_SCOPES || 'public,interactions,member,admin').split(','));
report.scopes = [...scopes];

function redact(message) {
    for (const account of Object.values(fixtures || {})) {
        for (const field of ['email', 'password']) {
            if (account?.[field]) message = message.replaceAll(account[field], '[QA hesabı]');
        }
    }
    return message;
}

async function context(options = {}) {
    const value = await browser.newContext({ locale: 'tr-TR', timezoneId: 'Europe/Istanbul', ...options });
    await value.route('**/*', route => {
        const requested = new URL(route.request().url());
        if (requested.origin === origin || ['data:', 'blob:'].includes(requested.protocol)) return route.continue();
        if (!seenBlocked.has(requested.origin)) { seenBlocked.add(requested.origin); report.blockedExternalRequests.push(requested.origin); }
        return route.abort('blockedbyclient');
    });
    return value;
}

async function check(name, callback) {
    try { await callback(); report.checks.push({ name, passed: true }); }
    catch (error) { const message = redact(error.message); report.checks.push({ name, passed: false, error: message }); console.log(JSON.stringify({ failure: name, error: message.slice(0, 1200) })); }
}

async function ready(page, route, expected = 200, allowRedirects = false) {
    const response = await page.goto(baseURL + route, { waitUntil: 'networkidle' });
    assert.equal(response.status(), expected, `${route} HTTP durumu`);
    if (!allowRedirects) assert.equal(new URL(page.url()).pathname, new URL(route, baseURL).pathname, `${route} beklenmeyen yönlendirme`);
    await page.waitForFunction(() => typeof window.Alpine !== 'undefined');
    await page.evaluate(() => document.fonts.ready);
}

async function noOverflow(page, name) {
    const dimensions = await page.evaluate(() => {
        const clippedHeadings = [];
        for (const heading of document.querySelectorAll('h1,h2,h3')) {
            const bounds = heading.getBoundingClientRect();
            if (!bounds.width || !bounds.height) continue;
            if (heading.scrollWidth > heading.clientWidth + 1) clippedHeadings.push(heading.textContent.trim());
            for (let ancestor = heading.parentElement; ancestor; ancestor = ancestor.parentElement) {
                if (!['hidden', 'clip'].includes(getComputedStyle(ancestor).overflowX)) continue;
                const clip = ancestor.getBoundingClientRect();
                if (bounds.right > clip.right + 1 || bounds.left < clip.left - 1) clippedHeadings.push(heading.textContent.trim());
            }
        }
        return { width: document.documentElement.clientWidth, scroll: document.documentElement.scrollWidth, clippedHeadings };
    });
    assert.ok(dimensions.scroll <= dimensions.width + 1, `${name}: yatay taşma ${dimensions.scroll}px / ${dimensions.width}px`);
    assert.deepEqual(dimensions.clippedHeadings, [], `${name}: panel sınırlarında kesilen başlık olmamalı`);
}

async function accessibility(page, name) {
    const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    assert.deepEqual(violations.map(item => ({ id: item.id, impact: item.impact, nodes: item.nodes.map(node => ({ target: node.target, summary: node.failureSummary })) })), [], name);
}

async function screenshot(page, file) {
    await page.screenshot({ path: path.join(output, file), fullPage: true });
    report.screenshots.push(file);
}

async function signIn(page, account) {
    await ready(page, '/giris');
    await page.getByLabel('E-posta adresi').fill(account.email);
    await page.getByLabel('Parola').fill(account.password);
    await page.getByRole('button', { name: 'Giriş Yap', exact: true }).click();
    await page.waitForLoadState('networkidle');
    assert.notEqual(new URL(page.url()).pathname, '/giris', 'QA hesabının girişi tamamlanmalı');
}

const widths = [320, 390, 768, 1280, 1920];
const routes = ['/', '/hakkimda', '/projelerim', '/yorumlar', '/saat-sayac', '/iletisim', '/gizlilik', '/kullanim-kosullari', '/uyelik-aydinlatma', '/giris', '/kayit', '/sifremi-unuttum'];

try {
    if (scopes.has('public')) for (const width of widths) {
        for (const theme of ['light', 'dark']) {
            const ctx = await context({ viewport: { width, height: 900 }, colorScheme: theme });
            const page = await ctx.newPage();
            const pageErrors = [];
            page.on('pageerror', error => pageErrors.push(error.message));
            for (const route of routes) {
                pageErrors.length = 0;
                await check(`${width}px ${theme} ${route} responsive/erişilebilirlik`, async () => {
                    await ready(page, route);
                    if (route === '/') await screenshot(page, `home-${width}-${theme}.png`);
                    if (route === '/saat-sayac' && [390, 1280].includes(width)) await screenshot(page, `clock-${width}-${theme}.png`);
                    await noOverflow(page, route); await accessibility(page, route);
                    assert.equal(await page.locator('html').getAttribute('data-theme'), theme);
                    assert.equal(await page.locator('html').getAttribute('lang'), 'tr');
                    assert.equal((await page.locator('meta[name="description"]').getAttribute('content')).trim().length > 0, true);
                    assert.deepEqual(pageErrors.splice(0), [], 'Tarayıcı JavaScript hataları');
                });
            }
            await check(`${width}px ${theme} 404 hata sayfası`, async () => { await ready(page, '/qa-bulunmayan-sayfa', 404); await noOverflow(page, '404'); await accessibility(page, '404'); });
            await ctx.close();
            console.log(JSON.stringify({ completed: `${width}px ${theme}`, checks: report.checks.length, failures: report.checks.filter(item => !item.passed).length }));
        }
    }

    if (scopes.has('interactions')) {
    const ctx = await context({ viewport: { width: 390, height: 844 }, colorScheme: 'dark', reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    await check('Mobil menü, klavye slider ve azaltılmış hareket', async () => {
        await ready(page, '/');
        await page.getByRole('button', { name: 'Menüyü aç', exact: true }).click();
        await page.locator('#mobile-nav').waitFor({ state: 'visible' });
        await page.keyboard.press('Escape'); await page.locator('#mobile-nav').waitFor({ state: 'hidden' });
        assert.equal(await page.locator('.hero-slide:visible').count(), 1);
        await page.getByRole('button', { name: 'Sonraki', exact: true }).click();
        assert.equal(await page.getByRole('button', { name: '2. slayt' }).getAttribute('aria-pressed'), 'true');
        assert.equal(await page.locator('.hero-slide:visible').count(), 1);
        await page.keyboard.press('ArrowRight'); assert.equal(await page.getByRole('button', { name: '3. slayt' }).getAttribute('aria-pressed'), 'true');
        await page.getByRole('button', { name: '1. slayt' }).click();
        assert.equal(await page.getByRole('button', { name: '1. slayt' }).getAttribute('aria-pressed'), 'true');
        const motion = await page.evaluate(() => ({ reduced: matchMedia('(prefers-reduced-motion: reduce)').matches, duration: getComputedStyle(document.querySelector('.hero-panel .button')).transitionDuration }));
        assert.equal(motion.reduced, true); assert.ok(motion.duration.split(',').every(duration => parseFloat(duration) <= .001));
    });
    await check('Tema seçimi tekrar yüklemede korunur', async () => {
        await ready(page, '/'); assert.equal(await page.locator('html').getAttribute('data-theme'), 'dark');
        await page.getByRole('button', { name: 'Açık temaya geç', exact: true }).click();
        await page.reload({ waitUntil: 'networkidle' }); assert.equal(await page.locator('html').getAttribute('data-theme'), 'light');
        assert.equal(await page.evaluate(() => localStorage.getItem('madematik-theme')), 'light');
    });
    await check('Kayıtlı koyu temada yalnız doğru marka PNG yüklenir ve seçim korunur', async () => {
        const logoContext = await context({ viewport: { width: 390, height: 844 }, colorScheme: 'light' });
        try {
            await logoContext.addInitScript(() => {
                if (localStorage.getItem('madematik-theme') === null) localStorage.setItem('madematik-theme', 'dark');
            });
            const logoPage = await logoContext.newPage();
            const requestedLogos = [];
            logoPage.on('request', request => {
                const pathname = new URL(request.url()).pathname;
                if (/\/assets\/brand\/logos\/logo-header-(light|dark)\.png$/.test(pathname)) requestedLogos.push(pathname);
            });
            await ready(logoPage, '/');
            const headerLogo = logoPage.locator('header [data-brand-logo]');
            assert.equal(await logoPage.locator('html').getAttribute('data-theme'), 'dark');
            assert.equal(await headerLogo.getAttribute('src'), origin + '/assets/brand/logos/logo-header-dark.png');
            assert.equal(await headerLogo.isVisible(), true);
            assert.equal(await headerLogo.evaluate(image => image.complete && image.naturalWidth > 0), true);
            assert.ok(requestedLogos.includes('/assets/brand/logos/logo-header-dark.png'), 'İlk yüklemede koyu tema PNG istenmeli');
            assert.ok(!requestedLogos.includes('/assets/brand/logos/logo-header-light.png'), 'İlk yüklemede açık tema PNG istenmemeli');
            await logoPage.getByRole('button', { name: 'Açık temaya geç', exact: true }).click();
            await logoPage.waitForFunction(() => {
                const headerImage = document.querySelector('header [data-brand-logo]');
                return [...document.querySelectorAll('[data-brand-logo]')].every(image => image.src === image.dataset.lightSrc)
                    && headerImage.complete && headerImage.naturalWidth > 0;
            });
            assert.equal(await headerLogo.getAttribute('src'), origin + '/assets/brand/logos/logo-header-light.png');
            requestedLogos.length = 0;
            await logoPage.reload({ waitUntil: 'networkidle' });
            assert.equal(await logoPage.locator('html').getAttribute('data-theme'), 'light');
            assert.equal(await headerLogo.getAttribute('src'), origin + '/assets/brand/logos/logo-header-light.png');
            assert.equal(await logoPage.evaluate(() => localStorage.getItem('madematik-theme')), 'light');
            assert.ok(!requestedLogos.includes('/assets/brand/logos/logo-header-dark.png'), 'Kaydedilen açık tema yeniden yüklenirken koyu PNG istenmemeli');
        } finally { await logoContext.close(); }
    });
    await check('Gerçek saat ve sayacın başlat/duraklat/yeniden aç/devam/sıfırla akışı', async () => {
        await ready(page, '/saat-sayac');
        const displayedClock = await page.locator('.clock-face').first().textContent();
        assert.match(displayedClock, /^\d{2}:\d{2}:\d{2}$/);
        await page.getByRole('tab', { name: 'SAYAÇ' }).click();
        await page.getByLabel('Saat', { exact: true }).fill('0'); await page.getByLabel('Dakika', { exact: true }).fill('0'); await page.getByLabel('Saniye', { exact: true }).fill('12');
        await page.getByRole('button', { name: 'Başlat', exact: true }).click();
        await page.waitForTimeout(1100); await page.getByRole('button', { name: 'Duraklat', exact: true }).click();
        const paused = await page.locator('output').textContent();
        await page.reload({ waitUntil: 'networkidle' }); assert.equal(await page.locator('output').textContent(), paused);
        await page.getByRole('button', { name: 'Devam Et', exact: true }).click();
        const first = await page.evaluate(() => JSON.parse(localStorage.getItem('madematik-clock-v1')));
        await page.reload({ waitUntil: 'networkidle' });
        const restored = await page.evaluate(() => JSON.parse(localStorage.getItem('madematik-clock-v1')));
        assert.equal(first.endsAt, restored.endsAt); assert.equal(restored.running, true);
        await page.getByRole('button', { name: 'Sıfırla', exact: true }).click();
        assert.equal(await page.locator('output').textContent(), '00:00:12');
        assert.equal((await page.evaluate(() => JSON.parse(localStorage.getItem('madematik-clock-v1')))).startedAt, null);
        await page.getByLabel('Saniye', { exact: true }).fill('0'); await page.getByRole('button', { name: 'Başlat', exact: true }).click();
        assert.equal(await page.getByText('Lütfen sıfırdan büyük bir süre seçin.', { exact: true }).isVisible(), true);
    });
    await check('Tam ekran büyüt/küçült ve azaltılmış hareket erişilebilirliği', async () => {
        await ready(page, '/saat-sayac'); await page.getByRole('button', { name: 'Büyüt', exact: true }).click();
        await page.getByRole('button', { name: 'Küçült', exact: true }).waitFor({ state: 'visible' });
        await page.getByRole('button', { name: 'Küçült', exact: true }).click();
        await page.getByRole('button', { name: 'Büyüt', exact: true }).waitFor({ state: 'visible' });
        await accessibility(page, 'Saat ve sayaç azaltılmış hareket');
    });
    await check('Tam ekran API bulunmadığında büyütme ve klavye odağı', async () => {
        await ready(page, '/saat-sayac');
        await page.evaluate(() => Object.defineProperty(Element.prototype, 'requestFullscreen', { value: undefined, configurable: true }));
        await page.getByRole('tab', { name: 'SAYAÇ', exact: true }).click();
        await page.getByRole('button', { name: 'Büyüt', exact: true }).click();
        const dialog = page.getByRole('dialog', { name: 'Saat & Sayaç', exact: true });
        await dialog.waitFor({ state: 'visible' });
        await accessibility(page, 'Büyütülmüş saat odağı'); await noOverflow(page, 'Büyütülmüş saat');
        await dialog.getByRole('button', { name: 'Sıfırla', exact: true }).focus();
        await page.keyboard.press('Tab');
        assert.equal(await page.getByRole('tab', { name: 'SAYAÇ', exact: true }).evaluate(element => element === document.activeElement), true);
        await page.keyboard.press('Shift+Tab');
        assert.equal(await dialog.getByRole('button', { name: 'Sıfırla', exact: true }).evaluate(element => element === document.activeElement), true);
        await page.keyboard.press('Escape'); await dialog.waitFor({ state: 'hidden' });
        assert.equal(await page.evaluate(() => document.body.style.overflow), '');
    });
    await check('Proje detay bağlantıları ve fiziksel bağımsız uygulama adresleri', async () => {
        await ready(page, '/projelerim');
        const links = await page.locator('a').evaluateAll(anchors => anchors.map(anchor => ({ text: anchor.textContent.trim(), href: anchor.getAttribute('href') })));
        const detail = links.find(link => link.href?.includes('/projelerim/') && link.text.includes('Projeyi İncele'));
        if (detail) { await ready(page, new URL(detail.href, baseURL).pathname); await noOverflow(page, 'Proje detayı'); await accessibility(page, 'Proje detayı'); }
        assert.ok(links.some(link => link.href === 'https://awa.madematik.com'), 'aWa bağımsız bağlantısı');
        assert.ok(!links.some(link => /https:\/\/(draw|geomat|avcell)\./i.test(link.href || '')), 'Bağımsız uygulamalar için yeni subdomain oluşturulmamalı');
    });
    await check('Proje arama formu ve boş sonuç durumu', async () => {
        await ready(page, '/projelerim');
        await page.getByLabel('Projelerde ara', { exact: true }).fill('QA-Bulunmayan-Proje');
        await page.getByRole('button', { name: 'Filtrele', exact: true }).click(); await page.waitForLoadState('networkidle');
        assert.equal(new URL(page.url()).searchParams.get('q'), 'QA-Bulunmayan-Proje');
        assert.equal(await page.getByText('Bu aramaya uygun proje yok', { exact: true }).isVisible(), true);
        await page.getByRole('link', { name: 'Filtreleri temizle', exact: true }).first().click(); await page.waitForLoadState('networkidle');
        assert.equal(new URL(page.url()).search, '');
    });
    await check('İngilizce yayınları ve harici font/network isteği yok', async () => {
        for (const route of ['/en', '/en/about', '/en/projects']) await ready(page, route, 404);
        assert.deepEqual(report.blockedExternalRequests, []);
    });
    await ctx.close();
    }

    if (scopes.has('member') && fixtures?.member) {
        const memberContext = await context({ viewport: { width: 390, height: 844 } });
        const memberPage = await memberContext.newPage();
        await check('Doğrulanmış üye profil/bildirim/yorum yanıtları erişilebilirliği', async () => {
            await signIn(memberPage, fixtures.member);
            for (const route of ['/profilim', '/bildirimler', '/yorumlar']) { await ready(memberPage, route); await noOverflow(memberPage, route); await accessibility(memberPage, route); }
            const reply = memberPage.getByRole('button', { name: 'Yanıtla', exact: true }).first();
            if (await reply.count()) { await reply.click(); assert.equal(await reply.getAttribute('aria-expanded'), 'true'); await accessibility(memberPage, 'Açık yanıt formu'); }
        });
        await check('Doğrulanmış üye 320px koyu tema taşma/erişilebilirlik', async () => {
            await memberPage.setViewportSize({ width: 320, height: 844 }); await memberPage.emulateMedia({ colorScheme: 'dark' });
            for (const route of ['/profilim', '/bildirimler', '/yorumlar']) { await ready(memberPage, route); await noOverflow(memberPage, route); await accessibility(memberPage, route); }
        });
        await memberContext.close();
    }

    if (scopes.has('admin') && fixtures?.admin) {
        for (const width of [390, 1280]) for (const theme of ['light', 'dark']) {
            const adminContext = await context({ viewport: { width, height: 900 }, colorScheme: theme });
            const adminPage = await adminContext.newPage();
            const pageErrors = [];
            adminPage.on('pageerror', error => pageErrors.push(error.message));
            let authenticated = false;
            await check(`${width}px ${theme} yönetici giriş ve parola onayı`, async () => {
                await signIn(adminPage, fixtures.admin);
                await ready(adminPage, '/admin', 200, true);
                assert.equal(new URL(adminPage.url()).pathname, '/sifre-onayi');
                await noOverflow(adminPage, 'Parola onayı'); await accessibility(adminPage, 'Parola onayı');
                await adminPage.getByLabel('Mevcut parola').fill(fixtures.admin.password);
                await adminPage.getByRole('button', { name: 'Doğrula ve Devam Et', exact: true }).click();
                await adminPage.waitForLoadState('networkidle');
                assert.equal(new URL(adminPage.url()).pathname, '/admin');
                authenticated = true;
            });
            if (authenticated) {
                const adminRoutes = ['/admin', '/admin/projects', '/admin/projects/create', '/admin/categories', '/admin/categories/create', '/admin/comments', '/admin/users', '/admin/contact-messages', '/admin/settings', '/admin/activity'];
                for (const route of adminRoutes) {
                    pageErrors.length = 0;
                    await check(`${width}px ${theme} ${route} yönetim/erişilebilirlik`, async () => {
                        await ready(adminPage, route);
                        await noOverflow(adminPage, route); await accessibility(adminPage, route);
                        assert.equal(await adminPage.locator('html').getAttribute('data-theme'), theme);
                        assert.equal(await adminPage.locator('meta[name="robots"]').getAttribute('content'), 'noindex,nofollow');
                        assert.deepEqual(pageErrors.splice(0), [], 'Yönetim JavaScript hataları');
                        if (route === '/admin') await screenshot(adminPage, `admin-${width}-${theme}.png`);
                    });
                }
                await check(`${width}px ${theme} yönetici düzenleme ve mesaj detayları`, async () => {
                    for (const [listing, pattern] of [['/admin/projects', /\/admin\/projects\/\d+\/edit$/], ['/admin/categories', /\/admin\/categories\/\d+\/edit$/], ['/admin/users', /\/admin\/users\/\d+\/edit$/], ['/admin/contact-messages', /\/admin\/contact-messages\/\d+$/]]) {
                        await ready(adminPage, listing);
                        const links = await adminPage.locator('main a[href]').evaluateAll(anchors => anchors.map(anchor => anchor.getAttribute('href')));
                        const detail = links.find(link => pattern.test(new URL(link, baseURL).pathname));
                        assert.ok(detail, `${listing} QA örneği ayrıntı bağlantısı`);
                        await ready(adminPage, new URL(detail, baseURL).pathname);
                        await noOverflow(adminPage, 'Yönetim ayrıntı'); await accessibility(adminPage, 'Yönetim ayrıntı');
                    }
                });
                if (width === 390) await check(`${width}px ${theme} yönetim mobil menüsü`, async () => {
                    await ready(adminPage, '/admin');
                    const navigation = adminPage.locator('#admin-navigation');
                    assert.equal(await navigation.isVisible(), false);
                    const toggle = adminPage.locator('button[aria-controls="admin-navigation"]');
                    await toggle.click(); await navigation.waitFor({ state: 'visible' });
                    assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
                    await noOverflow(adminPage, 'Açık yönetim menüsü'); await accessibility(adminPage, 'Açık yönetim menüsü');
                    await toggle.click(); await navigation.waitFor({ state: 'hidden' });
                });
            }
            await adminContext.close();
            console.log(JSON.stringify({ completed: `${width}px ${theme} admin`, checks: report.checks.length, failures: report.checks.filter(item => !item.passed).length }));
        }
    }

    if (scopes.has('admin-reflow') && fixtures?.admin) {
        for (const theme of ['light', 'dark']) {
            const reflowContext = await context({ viewport: { width: 320, height: 900 }, colorScheme: theme });
            const reflowPage = await reflowContext.newPage();
            let authenticated = false;
            await check(`${theme} yönetim reflow giriş/parola onayı`, async () => {
                await signIn(reflowPage, fixtures.admin);
                await ready(reflowPage, '/admin', 200, true);
                await reflowPage.getByLabel('Mevcut parola').fill(fixtures.admin.password);
                await reflowPage.getByRole('button', { name: 'Doğrula ve Devam Et', exact: true }).click();
                await reflowPage.waitForLoadState('networkidle');
                assert.equal(new URL(reflowPage.url()).pathname, '/admin');
                authenticated = true;
            });
            if (authenticated) for (const width of [320, 390, 1280]) for (const route of ['/admin', '/admin/projects/create']) for (const longName of [false, true]) {
                await check(`${width}px ${theme} ${route} reflow ${longName ? '100 karakter ad' : 'standart ad'}`, async () => {
                    await reflowPage.setViewportSize({ width, height: 900 });
                    await ready(reflowPage, route);
                    if (longName) await reflowPage.locator('header > p').evaluate(element => { element.textContent = 'Deneme'.repeat(16) + 'Test'; });
                    await noOverflow(reflowPage, 'Yönetim üst çubuğu'); await accessibility(reflowPage, 'Yönetim reflow');
                    if (width === 320 && route === '/admin' && longName) await screenshot(reflowPage, `admin-320-${theme}-long-name.png`);
                });
            }
            await reflowContext.close();
        }
    }
} finally {
    await browser.close();
    report.finishedAt = new Date().toISOString();
    report.passed = report.checks.filter(item => item.passed).length;
    report.failed = report.checks.length - report.passed;
    await writeFile(path.join(output, 'browser-report.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ report: 'storage/runtime/qa/browser-report.json', passed: report.passed, failed: report.failed, screenshots: report.screenshots.length }));
}
if (report.failed) process.exitCode = 1;
