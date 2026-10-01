/*
 * Service worker regression test (PWA Phase 1 + Phase 2).
 *
 * PHPUnit hanya bisa memeriksa isi berkas sw.js secara statis (tests/Feature/*).
 * Skrip ini benar-benar MENJALANKAN sw.js di dalam VM dengan Cache Storage,
 * fetch, dan Push API tiruan, lalu memverifikasi keputusan runtime-nya:
 *
 *   FASE 1 — routing cache:
 *     halaman terautentikasi, media privat (/storage), dan balasan ber-sesi
 *     TIDAK PERNAH tersimpan; aset statis publik boleh di-cache; cache versi
 *     lama dibuang saat activate.
 *
 *   FASE 2 — push:
 *     notifikasi tampil dari payload, URL tujuan selalu dikunci ke same-origin
 *     (anti open-redirect), payload rusak tidak melempar, dan klik notifikasi
 *     membuka/memfokuskan tab LRS yang benar. Handler push TIDAK menyentuh
 *     Cache Storage sama sekali.
 *
 * Jalankan dari root proyek:
 *     node tests/JavaScript/service-worker-routing.test.js public/sw.js
 *
 * Exit code 1 bila ada pemeriksaan yang gagal. Tidak butuh dependensi apa pun
 * (hanya modul bawaan Node).
 */
const fs = require('fs');
const vm = require('vm');

const src = fs.readFileSync(process.argv[2] || 'public/sw.js', 'utf8');

// Nama cache dibaca dari sumber, bukan di-hardcode, supaya menaikkan
// SW_VERSION tidak membuat pengujian ini gagal palsu.
const version = (src.match(/const SW_VERSION = '([^']+)'/) || [])[1];
if (!version) {
    console.error('SW_VERSION tidak ditemukan di ' + (process.argv[2] || 'public/sw.js'));
    process.exit(1);
}
const STATIC_CACHE = 'lrs-static-' + version;
const RUNTIME_CACHE = 'lrs-runtime-' + version;

const listeners = {};
const store = new Map(); // cacheName -> Map(url -> fake Response)

function fakeResponse(url, { status = 200, type = 'basic', headers = {} } = {}) {
    const lower = Object.fromEntries(Object.entries(headers).map(([k, v]) => [k.toLowerCase(), v]));
    return {
        url, status, type, ok: status >= 200 && status < 300,
        headers: {
            get: (n) => lower[n.toLowerCase()] ?? null,
            has: (n) => Object.prototype.hasOwnProperty.call(lower, n.toLowerCase()),
        },
        clone() { return fakeResponse(url, { status, type, headers }); },
    };
}

// --- Push API tiruan -------------------------------------------------------
const shown = [];            // notifikasi yang ditampilkan
const opened = [];           // URL yang dibuka lewat clients.openWindow()
let openClients = [];        // tab LRS yang sedang terbuka

global.self = {
    addEventListener: (t, f) => { listeners[t] = f; },
    skipWaiting: () => {},
    clients: {
        claim: async () => {},
        matchAll: async () => openClients,
        openWindow: async (url) => { opened.push(url); return null; },
    },
    registration: {
        navigationPreload: null,
        showNotification: async (title, options) => { shown.push({ title, options }); },
    },
    location: { origin: 'https://lrs.test' },
};

const resolve = (req) => new URL(req.url ?? req, 'https://lrs.test').href;

global.caches = {
    open: async (name) => {
        if (!store.has(name)) store.set(name, new Map());
        const m = store.get(name);
        return {
            add: async (req) => { const u = resolve(req); m.set(u, fakeResponse(u)); },
            match: async (req) => m.get(resolve(req)),
            put: async (req, res) => { m.set(resolve(req), res); },
        };
    },
    keys: async () => [...store.keys()],
    delete: async (name) => store.delete(name),
};

let network = async (req) => fakeResponse(typeof req === 'string' ? req : req.url);
global.fetch = (req) => network(req);

// Browser Request resolves root-relative URLs against the SW scope; Node's does not.
global.Request = class {
    constructor(url, opts = {}) {
        this.url = new URL(url, 'https://lrs.test').href;
        this.method = opts.method || 'GET';
    }
};

vm.runInThisContext(src);

function request(url, { method = 'GET', mode = 'no-cors' } = {}) {
    return { url, method, mode };
}

function dispatch(req) {
    let responded = false;
    let respondedPromise = null;
    const waits = [];
    listeners.fetch({
        request: req,
        respondWith: (p) => { responded = true; respondedPromise = Promise.resolve(p); },
        waitUntil: (p) => waits.push(p),
        preloadResponse: undefined,
    });
    return { responded, respondedPromise, waits };
}

/** Kirim event push dengan payload apa adanya (string / objek / rusak). */
async function push(rawPayload) {
    const waits = [];
    listeners.push({
        data: rawPayload === undefined ? null : { json: () => { if (rawPayload === 'BROKEN') throw new Error('bukan JSON'); return rawPayload; } },
        waitUntil: (p) => waits.push(p),
    });
    await Promise.all(waits);
}

/** Kirim event notificationclick untuk notifikasi terakhir yang tampil. */
async function click(index = shown.length - 1) {
    const waits = [];
    listeners.notificationclick({
        notification: { data: shown[index].options.data, close: () => {} },
        waitUntil: (p) => waits.push(p),
    });
    await Promise.all(waits);
}

const results = [];
function check(label, condition, detail = '') {
    results.push({ label, pass: !!condition });
    console.log((condition ? 'PASS' : 'FAIL') + '  ' + label + (detail ? '  [' + detail + ']' : ''));
}

const settle = () => new Promise((r) => setTimeout(r, 25));

(async () => {
    // Install (precache).
    const installWaits = [];
    listeners.install({ waitUntil: (p) => installWaits.push(p) });
    await Promise.all(installWaits);
    check('install precaches public assets', store.get(STATIC_CACHE).has('https://lrs.test/offline.html'));

    // 1. Navigation while online -> network response, never cache.
    network = async () => fakeResponse('https://lrs.test/admin/reports', { headers: { 'Content-Type': 'text/html' } });
    let r = dispatch(request('https://lrs.test/admin/reports', { mode: 'navigate' }));
    let res = await r.respondedPromise;
    check('navigation is network-served', res && res.url.endsWith('/admin/reports'), 'status=' + (res && res.status));

    // 2. Navigation while offline -> offline.html, NOT stale private HTML.
    network = async () => { throw new Error('offline'); };
    r = dispatch(request('https://lrs.test/admin/reports', { mode: 'navigate' }));
    res = await r.respondedPromise;
    check('offline navigation falls back to offline.html', res && res.url === 'https://lrs.test/offline.html', res && res.url);

    // 3. Offline navigation to a report detail must not serve cached data.
    r = dispatch(request('https://lrs.test/coach/reports/42', { mode: 'navigate' }));
    res = await r.respondedPromise;
    check('offline report detail never serves cached data', res && res.url === 'https://lrs.test/offline.html', res && res.url);

    // 4. Non-navigation fetches of app routes -> passthrough (no SW involvement).
    const passthroughPaths = ['/admin/reports', '/coach/reports', '/attendance', '/storage/reports/1/proof.jpg', '/api/reports', '/reports/9/download', '/notifications'];
    for (const path of passthroughPaths) {
        r = dispatch(request('https://lrs.test' + path));
        check('passthrough (uncached): ' + path, r.responded === false);
    }

    // 5. POST is never intercepted.
    r = dispatch(request('https://lrs.test/coach/reports', { method: 'POST' }));
    check('POST is never intercepted', r.responded === false);

    // 6. Public static asset -> cached, served from cache afterwards.
    network = async () => fakeResponse('https://lrs.test/icons/icon-192.png', { headers: { 'Content-Type': 'image/png' } });
    r = dispatch(request('https://lrs.test/icons/icon-192.png'));
    await r.respondedPromise;
    await settle();
    check('public asset is cached', store.get(STATIC_CACHE).has('https://lrs.test/icons/icon-192.png'));

    // 7. CDN asset -> cached in the runtime cache.
    const cdn = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css';
    network = async () => fakeResponse(cdn, { type: 'cors' });
    r = dispatch(request(cdn));
    await r.respondedPromise;
    await settle();
    check('CDN asset is cached', store.get(RUNTIME_CACHE).has(cdn));

    // 8. Session-bearing / private responses are refused.
    const refusalCases = [
        ['Set-Cookie response is not cached', '/icons/private.png', { headers: { 'Set-Cookie': 'laravel_session=abc' } }],
        ['no-store/private response is not cached', '/icons/private2.png', { headers: { 'Cache-Control': 'no-store, private' } }],
        ['opaque response is not cached', '/icons/redirected.png', { type: 'opaqueredirect' }],
        ['non-200 response is not cached', '/icons/missing.png', { status: 404 }],
    ];
    for (const [label, path, opts] of refusalCases) {
        network = async () => fakeResponse('https://lrs.test' + path, opts);
        r = dispatch(request('https://lrs.test' + path));
        await r.respondedPromise;
        await settle();
        check(label, !store.get(STATIC_CACHE).has('https://lrs.test' + path));
    }

    // 9. Activate: old cache versions are purged.
    store.set('lrs-static-v0.9.0', new Map([['https://lrs.test/old.css', fakeResponse('https://lrs.test/old.css')]]));
    store.set('unrelated-cache', new Map([['https://lrs.test/keep.css', fakeResponse('https://lrs.test/keep.css')]]));
    const activateWaits = [];
    listeners.activate({ waitUntil: (p) => activateWaits.push(p) });
    await Promise.all(activateWaits);
    check('old cache version purged on activate', !store.has('lrs-static-v0.9.0'));
    check('unrelated cache is left alone', store.has('unrelated-cache'));

    // =====================================================================
    // FASE 2 — Web Push
    // =====================================================================

    // 11. Payload normal -> satu notifikasi dengan judul/body/url yang benar.
    await push({
        title: 'Pengingat Laporan',
        body: 'Anda memiliki 2 sesi mengajar yang belum dilaporkan.',
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-72.png',
        tag: 'lrs-report-reminder',
        data: { type: 'report_reminder', notification_id: 'uuid-1', url: '/coach/reports' },
    });
    check('push menampilkan satu notifikasi', shown.length === 1);
    check('judul & body diteruskan apa adanya', shown[0].title === 'Pengingat Laporan'
        && shown[0].options.body.includes('2 sesi mengajar'));
    check('data notifikasi diteruskan ke klik', shown[0].options.data.notification_id === 'uuid-1'
        && shown[0].options.data.url === '/coach/reports');

    // 12. Klik -> tab LRS dibuka pada halaman tujuan.
    openClients = [];
    await click();
    check('klik tanpa tab terbuka -> openWindow ke halaman tujuan',
        opened.length === 1 && opened[0] === '/coach/reports', opened.join(', '));

    // 13. Klik dengan tab LRS yang sudah terbuka -> difokuskan & diarahkan.
    const focused = [];
    const navigated = [];
    openClients = [{
        url: 'https://lrs.test/admin/dashboard',
        focus: async () => { focused.push(true); },
        navigate: async (u) => { navigated.push(u); },
    }];
    opened.length = 0;
    await click();
    check('klik memfokuskan tab LRS yang sudah terbuka', focused.length === 1);
    check('tab yang sudah terbuka diarahkan ke halaman tujuan',
        navigated.length === 1 && navigated[0] === '/coach/reports', navigated.join(', '));
    check('klik tidak membuka tab baru bila tab LRS sudah ada', opened.length === 0);

    // 14. Tab milik origin lain tidak boleh dipakai/diarahkan.
    openClients = [{ url: 'https://situs-lain.test/x', focus: async () => {}, navigate: async () => {} }];
    opened.length = 0;
    await click();
    check('tab origin lain diabaikan -> buka tab LRS baru',
        opened.length === 1 && opened[0] === '/coach/reports', opened.join(', '));

    // 15. URL tujuan jahat selalu dikunci ke beranda.
    const hostile = [
        'https://jahat.example/phishing',
        '//jahat.example/phishing',
        'javascript:alert(1)',
        'http://jahat.example/',
    ];
    for (const url of hostile) {
        await push({ title: 'X', body: 'Y', data: { url } });
        const last = shown[shown.length - 1];
        check('URL eksternal ditolak: ' + url, last.options.data.url === '/');
    }

    // 16. Ikon eksternal tidak boleh dipakai.
    await push({ title: 'X', body: 'Y', icon: 'https://jahat.example/logo.png', badge: '//jahat.example/b.png' });
    const iconCase = shown[shown.length - 1];
    check('ikon eksternal jatuh ke ikon default',
        iconCase.options.icon === '/icons/icon-192.png' && iconCase.options.badge === '/icons/badge-72.png',
        iconCase.options.icon);

    // 17. Payload rusak / kosong tidak boleh melempar dan tetap menampilkan notifikasi.
    const before = shown.length;
    await push('BROKEN');
    await push(undefined);
    await push({});
    check('payload rusak/kosong tidak melempar & tetap tampil',
        shown.length === before + 3 && shown[before].title === 'Learning Report System',
        shown[before].title);

    // 18. Handler push tidak menyentuh Cache Storage.
    const cacheNamesBefore = [...store.keys()].sort().join(',');
    const cachedBefore = [...store.values()].flatMap((m) => [...m.keys()]).length;
    await push({
        title: 'Pengingat Laporan',
        body: 'Isi notifikasi rahasia',
        data: { type: 'report_reminder', notification_id: 'uuid-2', url: '/coach/reports' },
    });
    await click();
    const cachedAfter = [...store.values()].flatMap((m) => [...m.keys()]).length;
    check('push tidak menulis apa pun ke Cache Storage',
        cachedBefore === cachedAfter && cacheNamesBefore === [...store.keys()].sort().join(','));
    const allValues = [...store.values()].flatMap((m) => [...m.values()]);
    check('isi notifikasi tidak pernah masuk cache',
        !allValues.some((v) => JSON.stringify(v).includes('Isi notifikasi rahasia')));

    // 19. THE security invariant: no protected path may exist in any cache.
    const allKeys = [...store.values()].flatMap((m) => [...m.keys()]);
    const leaked = allKeys.filter((u) => /(\/admin|\/coach|\/attendance|\/classes|\/students|\/storage|\/api|\/reports|\/schedules|\/notifications|\/download)/.test(u));
    check('no protected path in any cache', leaked.length === 0, leaked.join(', ') || allKeys.length + ' asset(s) cached');

    const failed = results.filter((x) => !x.pass);
    console.log('\n' + (results.length - failed.length) + '/' + results.length + ' checks passed');
    process.exit(failed.length ? 1 : 0);
})();
