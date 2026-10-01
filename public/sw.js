/*
 * Learning Report System — Service Worker (PWA Phase 1 + Phase 2).
 *
 * STRATEGI CACHE (keamanan dulu, bukan kecepatan dulu):
 *
 *   1. Navigasi (dokumen HTML)  -> NETWORK ONLY.
 *      Halaman aplikasi ini semuanya di belakang auth Laravel dan berisi data
 *      sekolah/murid. Tidak ada satu pun HTML yang masuk cache, sehingga:
 *        - tidak ada halaman laporan/kehadiran basi yang tampil setelah logout,
 *        - tidak ada data pengguna lain yang bisa bocor dari cache bersama.
 *      Saat offline, navigasi jatuh ke /offline.html (halaman statis tanpa data).
 *
 *   2. Path aplikasi terproteksi (/admin, /coach, /attendance, /storage, ...)
 *      -> PASSTHROUGH. Tidak pernah dibaca dari cache, tidak pernah ditulis.
 *
 *   3. Aset statis publik (CSS, JS, font, ikon, manifest, offline.html)
 *      -> STALE-WHILE-REVALIDATE. Hanya same-origin dan hanya lewat allowlist
 *      prefix/ekstensi, sehingga rute dinamis tidak mungkin ikut ter-cache.
 *
 *   4. CDN publik (jsdelivr, Google Fonts) -> STALE-WHILE-REVALIDATE.
 *      Hanya balasan 200 non-opaque; tidak ada data pengguna di sana.
 *
 *   5. Selain itu (POST/PUT/DELETE, XHR/fetch API, unduhan, media privat)
 *      -> tidak disentuh sama sekali; browser yang menangani.
 *
 * Balasan dengan Set-Cookie / Cache-Control: no-store|private tidak pernah
 * disimpan — permintaan ber-sesi tidak boleh berakhir di cache.
 *
 * UPDATE: versi cache di-bump per rilis. SW baru langsung aktif (skipWaiting),
 * mengambil alih klien (clients.claim), lalu MENGHAPUS seluruh cache versi lama
 * sehingga UI tidak pernah disajikan dari aset basi.
 *
 * WAJIB SAAT RILIS: naikkan SW_VERSION setiap kali aset statis publik berubah
 * (ikon, offline.html, manifest, CSS/JS hasil build). Tanpa itu, precache lama
 * tetap dipakai sampai cache HTTP-nya kedaluwarsa.
 *
 * PUSH (Phase 2):
 *   - Handler `push` hanya MENAMPILKAN notifikasi dari payload yang dikirim
 *     server. Tidak ada data notifikasi yang disimpan di Cache Storage —
 *     daftar notifikasi tetap dibaca online dari Laravel (sumber kebenaran).
 *   - Handler `notificationclick` membuka URL tujuan yang divalidasi
 *     same-origin; URL absolut/eksternal ditolak dan jatuh ke beranda.
 *   - Subscription kedaluwarsa tidak dibuang di sini: server yang menghapusnya
 *     saat penyedia push membalas 404/410.
 *
 * CATATAN DEPLOY (nginx — Herd & container produksi):
 *   - Manifest sengaja bernama /manifest.json (bukan .webmanifest) karena nginx
 *     mengirim .webmanifest sebagai application/octet-stream dan browser
 *     menolak memasang aplikasi. Tidak perlu konfigurasi MIME tambahan.
 *   - Pastikan /sw.js tidak di-cache lama
 *     (nginx: `location = /sw.js { add_header Cache-Control "no-cache, no-store, must-revalidate"; }`).
 *   - Service worker hanya berjalan di HTTPS (atau localhost). Di http:// biasa
 *     registrasi di-skip dan aplikasi tetap berjalan normal.
 */

const SW_VERSION = 'v1.1.0';
const STATIC_CACHE = `lrs-static-${SW_VERSION}`;
const RUNTIME_CACHE = `lrs-runtime-${SW_VERSION}`;
const OFFLINE_URL = '/offline.html';

/* Tujuan default klik notifikasi bila payload tidak memuat URL yang sah. */
const DEFAULT_NOTIFICATION_URL = '/';

/* Ikon notifikasi default (same-origin, publik). */
const DEFAULT_NOTIFICATION_ICON = '/icons/icon-192.png';
const DEFAULT_NOTIFICATION_BADGE = '/icons/badge-72.png';

/* Aset publik same-origin yang aman di-precache (tanpa data pengguna). */
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/maskable-192.png',
    '/icons/maskable-512.png',
    '/icons/apple-touch-icon.png',
    '/icons/favicon-32.png',
    '/icons/badge-72.png',
];

/* Host CDN publik yang boleh di-cache (aset statis, bukan API). */
const CDN_HOSTS = [
    'cdn.jsdelivr.net',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
];

/* Prefix same-origin yang boleh masuk cache. Sengaja allowlist, bukan denylist:
   apa pun yang tidak cocok tidak akan pernah tersimpan. */
const STATIC_PATH_PREFIXES = ['/icons/', '/build/', '/css/', '/js/', '/fonts/', '/images/', '/img/'];

/* Ekstensi aset statis. '.json' sengaja TIDAK ikut (bisa berisi data API). */
const STATIC_EXTENSION = /\.(?:css|js|mjs|png|jpe?g|gif|svg|webp|avif|ico|woff2?|ttf|otf|eot|webmanifest)$/i;

/* Path aplikasi terproteksi: tidak pernah di-cache, tidak pernah dilayani cache.
   Termasuk /storage/ (media privat laporan: foto & video bukti) dan seluruh
   rute unduhan/ekspor. */
const PROTECTED_PATH_PREFIXES = [
    '/admin', '/coach', '/attendance', '/classes', '/students', '/pic',
    '/storage', '/api', '/reports', '/schedules', '/notifications',
    '/exports', '/export', '/download', '/login', '/logout', '/profile',
];

/* ------------------------------------------------------------------ */
/* Install: precache aset publik, lalu aktif segera (update aman).      */
/* ------------------------------------------------------------------ */
self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        // allSettled: satu aset gagal (mis. ikon belum ter-deploy) tidak boleh
        // menggagalkan instalasi SW.
        await Promise.allSettled(
            PRECACHE_URLS.map((url) => cache.add(new Request(url, { cache: 'reload' })))
        );
        await self.skipWaiting();
    })());
});

/* ------------------------------------------------------------------ */
/* Activate: buang cache versi lama, ambil alih klien.                  */
/* ------------------------------------------------------------------ */
self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        // 1. Hapus semua cache LRS dari versi sebelumnya.
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter((key) => key.startsWith('lrs-') && key !== STATIC_CACHE && key !== RUNTIME_CACHE)
                .map((key) => caches.delete(key))
        );

        // 2. Buang isi runtime cache lama agar aset (Bootstrap/Font) tidak basi
        //    setelah rilis baru; file-nya akan diambil ulang saat dibutuhkan.
        try { await caches.delete(RUNTIME_CACHE); } catch (e) { /* diabaikan */ }

        // 3. Navigation preload: navigasi tetap network-only, tetapi browser
        //    boleh menyiapkan request-nya lebih awal.
        if (self.registration.navigationPreload) {
            try { await self.registration.navigationPreload.enable(); } catch (e) { /* diabaikan */ }
        }

        await self.clients.claim();

        // 4. Beri tahu klien yang terbuka bahwa versi baru sudah aktif.
        const clients = await self.clients.matchAll({ type: 'window' });
        clients.forEach((client) => client.postMessage({ type: 'LRS_SW_ACTIVATED', version: SW_VERSION }));
    })());
});

/* ------------------------------------------------------------------ */
/* Fetch routing                                                        */
/* ------------------------------------------------------------------ */
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Hanya GET. POST/PUT/PATCH/DELETE (form, CSRF, unduhan) tidak pernah
    // disentuh service worker.
    if (request.method !== 'GET') return;

    let url;
    try { url = new URL(request.url); } catch (e) { return; }

    // Skema non-http (chrome-extension:, data:, blob:) -> lewat.
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;

    // 1. Navigasi HTML: network-only, offline -> /offline.html. Tidak ada
    //    halaman terautentikasi yang pernah dilayani dari cache.
    if (request.mode === 'navigate') {
        event.respondWith(handleNavigation(event));
        return;
    }

    const sameOrigin = url.origin === self.location.origin;

    // 2. Rute aplikasi terproteksi: jangan pernah cache/layani dari cache.
    if (sameOrigin && isProtectedPath(url.pathname)) return;

    // 3. Aset statis CDN publik.
    if (!sameOrigin) {
        if (CDN_HOSTS.includes(url.hostname)) {
            event.respondWith(staleWhileRevalidate(request, RUNTIME_CACHE));
        }
        return;
    }

    // 4. Aset statis same-origin (allowlist prefix + ekstensi).
    if (isStaticAsset(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request, STATIC_CACHE));
    }

    // 5. Sisanya: perilaku browser standar (tanpa cache SW).
});

/* ------------------------------------------------------------------ */
/* Helpers                                                              */
/* ------------------------------------------------------------------ */

function isProtectedPath(pathname) {
    return PROTECTED_PATH_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

function isStaticAsset(pathname) {
    if (!STATIC_PATH_PREFIXES.some((prefix) => pathname.startsWith(prefix))) return false;
    return STATIC_EXTENSION.test(pathname);
}

/** Hanya balasan publik yang boleh disimpan. */
function isCacheable(response) {
    if (!response) return false;
    if (response.status !== 200 || !response.ok) return false;
    // opaque/opaqueredirect tidak bisa diverifikasi -> jangan disimpan.
    if (response.type === 'opaque' || response.type === 'opaqueredirect') return false;

    const cacheControl = response.headers.get('Cache-Control') || '';
    if (/no-store|no-cache|private/i.test(cacheControl)) return false;

    // Balasan ber-sesi (Set-Cookie) tidak boleh masuk cache.
    if (response.headers.has('Set-Cookie')) return false;

    return true;
}

/** Network-only untuk dokumen; offline -> halaman statis tanpa data. */
async function handleNavigation(event) {
    try {
        if (event.preloadResponse) {
            const preloaded = await event.preloadResponse;
            if (preloaded) return preloaded;
        }
        return await fetch(event.request);
    } catch (error) {
        const cache = await caches.open(STATIC_CACHE);
        const offline = await cache.match(OFFLINE_URL) || await fetch(OFFLINE_URL).catch(() => null);
        if (offline) return offline;
        return new Response('Aplikasi sedang offline. Sambungkan kembali ke internet untuk membuka halaman ini.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store' },
        });
    }
}

/** Sajikan cache lebih dulu, perbarui di belakang layar. */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const network = fetch(request)
        .then((response) => {
            storeInCache(cache, request, response);
            return response;
        })
        // Hanya kegagalan JARINGAN yang sampai ke sini: storeInCache tidak
        // pernah melempar, sehingga kegagalan menulis cache tidak boleh
        // membatalkan balasan jaringan yang sebenarnya berhasil.
        .catch(() => null);

    if (cached) return cached;

    const response = await network;
    if (response) return response;

    return new Response('', { status: 504, statusText: 'Offline' });
}

/** Simpan salinan balasan bila aman. Kegagalan apa pun diabaikan. */
function storeInCache(cache, request, response) {
    try {
        if (!isCacheable(response)) return;
        cache.put(request, response.clone()).catch(() => { /* kuota penuh: diabaikan */ });
    } catch (error) {
        /* Balasan tidak bisa di-clone (body sudah terpakai): lewati cache,
           balasan tetap dikembalikan ke halaman. */
    }
}

/* ------------------------------------------------------------------ */
/* Pesan dari klien                                                     */
/* ------------------------------------------------------------------ */
self.addEventListener('message', (event) => {
    const data = event.data || {};

    // Dipakai saat logout: tidak ada data pengguna yang boleh tersisa.
    if (data.type === 'LRS_CLEAR_CACHES') {
        event.waitUntil((async () => {
            await caches.delete(RUNTIME_CACHE);
            const clients = await self.clients.matchAll({ type: 'window' });
            clients.forEach((client) => client.postMessage({ type: 'LRS_CACHES_CLEARED' }));
        })());
    }

    if (data.type === 'LRS_SKIP_WAITING') {
        self.skipWaiting();
    }
});

/* ------------------------------------------------------------------ */
/* Web Push (PWA Phase 2)                                               */
/* ------------------------------------------------------------------ */

/**
 * Tampilkan notifikasi dari payload yang dikirim server.
 *
 * Payload yang diterima dibatasi: title, body, icon, badge, tag, dan `data`
 * berisi type / notification_id / url. Handler ini hanya MENAMPILKAN — tidak
 * menulis apa pun ke Cache Storage, sehingga tidak ada data notifikasi
 * terautentikasi yang tersimpan offline.
 */
self.addEventListener('push', (event) => {
    const payload = readPushPayload(event);

    const title = typeof payload.title === 'string' && payload.title.trim() !== ''
        ? payload.title
        : 'Learning Report System';

    const data = (payload.data && typeof payload.data === 'object') ? payload.data : {};

    event.waitUntil(self.registration.showNotification(title, {
        body: typeof payload.body === 'string' ? payload.body : '',
        icon: safeIconUrl(payload.icon, DEFAULT_NOTIFICATION_ICON),
        badge: safeIconUrl(payload.badge, DEFAULT_NOTIFICATION_BADGE),
        tag: typeof payload.tag === 'string' && payload.tag !== '' ? payload.tag : undefined,
        // Hanya data navigasi yang diteruskan ke notificationclick. Tidak ada
        // isi laporan, token, atau identitas pengguna di sini.
        data: {
            url: safeTargetUrl(data.url),
            type: typeof data.type === 'string' ? data.type : null,
            notification_id: typeof data.notification_id === 'string' ? data.notification_id : null,
        },
    }));
});

/**
 * Buka/fokus LRS pada halaman yang dituju notifikasi.
 *
 * Tab LRS yang sudah terbuka difokuskan lalu diarahkan; bila tidak ada, tab
 * baru dibuka. URL selalu divalidasi same-origin.
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data || {};
    const target = safeTargetUrl(data.url);

    event.waitUntil((async () => {
        const clientList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of clientList) {
            if (new URL(client.url).origin !== self.location.origin) continue;

            await client.focus();

            // navigate() tidak tersedia di semua browser; bila tidak ada,
            // tab yang sudah fokus tetap terbuka di halaman LRS.
            if (typeof client.navigate === 'function') {
                try { await client.navigate(target); } catch (e) { /* diabaikan */ }
            }

            return;
        }

        await self.clients.openWindow(target);
    })());
});

/** Baca payload JSON dari event push; payload rusak tidak boleh melempar. */
function readPushPayload(event) {
    if (!event.data) return {};

    try {
        const parsed = event.data.json();
        return (parsed && typeof parsed === 'object') ? parsed : {};
    } catch (error) {
        // Payload bukan JSON (mis. dikirim kanal lain). Tampilkan notifikasi
        // generik tanpa data, jangan gagalkan event.
        return {};
    }
}

/**
 * Validasi URL tujuan klik.
 *
 * Hanya path internal same-origin yang diterima. URL absolut, protokol-relatif
 * (`//evil.example`), dan skema lain ditolak -> jatuh ke beranda. Ini mencegah
 * payload push dipakai sebagai open-redirect.
 */
function safeTargetUrl(raw) {
    if (typeof raw !== 'string' || raw === '') return DEFAULT_NOTIFICATION_URL;

    if (raw.charAt(0) !== '/' || raw.charAt(1) === '/') return DEFAULT_NOTIFICATION_URL;

    try {
        const url = new URL(raw, self.location.origin);
        if (url.origin !== self.location.origin) return DEFAULT_NOTIFICATION_URL;
        return url.pathname + url.search + url.hash;
    } catch (error) {
        return DEFAULT_NOTIFICATION_URL;
    }
}

/** Ikon notifikasi: hanya path internal; selain itu pakai default. */
function safeIconUrl(raw, fallback) {
    if (typeof raw !== 'string' || raw === '') return fallback;
    if (raw.charAt(0) !== '/' || raw.charAt(1) === '/') return fallback;

    try {
        const url = new URL(raw, self.location.origin);
        return url.origin === self.location.origin ? url.pathname : fallback;
    } catch (error) {
        return fallback;
    }
}
