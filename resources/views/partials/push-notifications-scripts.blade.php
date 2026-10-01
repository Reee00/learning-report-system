@php
    $vapidPublicKey = trim((string) config('webpush.vapid.public_key'));
@endphp

@if($vapidPublicKey !== '')
{{--
    PWA Phase 2 — aktivasi notifikasi perangkat (Web Push).

    ATURAN UX & KEAMANAN:
      - TIDAK ADA prompt izin otomatis saat halaman dimuat. Izin hanya diminta
        setelah user menekan "Aktifkan Notifikasi".
      - Status ditampilkan apa adanya: aktif, belum aktif, diblokir browser,
        atau tidak didukung. Tombol nonaktif bila memang tidak bisa dipakai.
      - Subscription dikirim ke Laravel lewat endpoint terautentikasi + CSRF;
        user_id tidak pernah dikirim dari klien.
      - Private key VAPID tidak ada di halaman ini — hanya public key, yang
        memang boleh diketahui browser.
      - Bila server menolak/menggagal, subscription lokal dibatalkan lagi supaya
        UI tidak pernah mengaku "aktif" padahal server tidak mengenalnya.
--}}
<script>
(function () {
    'use strict';

    var VAPID_PUBLIC_KEY = @json($vapidPublicKey);
    var SUBSCRIBE_URL = @json(route('push-subscriptions.store'));
    var UNSUBSCRIBE_URL = @json(route('push-subscriptions.destroy'));
    var CURRENT_USER_ID = @json((string) auth()->id());
    var SYNC_KEY = 'lrs_push_synced';

    var wrapper = document.getElementById('pushNotifyWrapper');
    if (!wrapper) return;

    var btn = document.getElementById('pushNotifyBtn');
    var icon = document.getElementById('pushNotifyIcon');
    var dot = document.getElementById('pushNotifyDot');
    var statusEl = document.getElementById('pushNotifyStatus');
    var toggleBtn = document.getElementById('pushNotifyToggle');
    var hintEl = document.getElementById('pushNotifyHint');

    var supported = 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window
        && window.isSecureContext === true;

    function setStatus(text) { statusEl.textContent = text; }

    function setHint(text) {
        hintEl.textContent = text || '';
        hintEl.classList.toggle('d-none', !text);
    }

    /** Tampilkan status + tombol. `action` = 'enable' | 'disable' | null. */
    function render(state, label, action) {
        setStatus(label);

        var active = state === 'active';
        icon.className = active ? 'bi bi-bell-fill' : 'bi bi-bell';
        dot.className = 'position-absolute top-0 start-100 translate-middle p-1 rounded-circle border border-light '
            + (active ? 'bg-success' : 'bg-secondary');
        dot.classList.remove('d-none');

        if (!action) {
            toggleBtn.classList.add('d-none');
            return;
        }

        toggleBtn.classList.remove('d-none');
        toggleBtn.disabled = false;
        toggleBtn.className = 'btn btn-sm w-100 ' + (action === 'disable' ? 'btn-outline-secondary' : 'btn-primary');
        toggleBtn.textContent = action === 'disable' ? 'Nonaktifkan di Perangkat Ini' : 'Aktifkan Notifikasi';
        toggleBtn.dataset.action = action;
    }

    function renderUnavailable(label, hint) {
        setStatus(label);
        setHint(hint);
        icon.className = 'bi bi-bell-slash';
        dot.classList.add('d-none');
        toggleBtn.classList.add('d-none');
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function post(url, method, body) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        });
    }

    /** applicationServerKey: base64url -> Uint8Array. */
    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var output = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i);
        return output;
    }

    function subscriptionPayload(subscription) {
        var json = subscription.toJSON();
        return {
            endpoint: json.endpoint,
            keys: { p256dh: json.keys.p256dh, auth: json.keys.auth },
            content_encoding: (window.PushManager && PushManager.supportedContentEncodings
                && PushManager.supportedContentEncodings.indexOf('aes128gcm') !== -1)
                ? 'aes128gcm' : 'aesgcm'
        };
    }

    /** Catat endpoint yang sudah tersinkron untuk user ini (localStorage aman). */
    function markSynced(endpoint) {
        try {
            window.localStorage.setItem(SYNC_KEY, JSON.stringify({
                endpoint: endpoint, user: CURRENT_USER_ID
            }));
        } catch (e) { /* mode privat: abaikan */ }
    }

    function alreadySynced(endpoint) {
        try {
            var raw = window.localStorage.getItem(SYNC_KEY);
            if (!raw) return false;
            var parsed = JSON.parse(raw);
            return parsed && parsed.endpoint === endpoint && parsed.user === CURRENT_USER_ID;
        } catch (e) { return false; }
    }

    function clearSynced() {
        try { window.localStorage.removeItem(SYNC_KEY); } catch (e) { /* diabaikan */ }
    }

    /**
     * Kirim subscription ke server. Mengembalikan true bila server menerimanya.
     * Bila server menolak, subscription lokal dibatalkan supaya perangkat tidak
     * mengira dirinya terdaftar padahal tidak.
     */
    function syncToServer(subscription) {
        return post(SUBSCRIBE_URL, 'POST', subscriptionPayload(subscription))
            .then(function (response) {
                if (response.ok) {
                    markSynced(subscription.endpoint);
                    return true;
                }
                return subscription.unsubscribe().catch(function () {}).then(function () {
                    clearSynced();
                    throw new Error('server menolak subscription (HTTP ' + response.status + ')');
                });
            });
    }

    function ensureSubscription(registration) {
        return registration.pushManager.getSubscription().then(function (existing) {
            if (existing) return existing;
            return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });
        });
    }

    function refresh() {
        if (!supported) {
            renderUnavailable('Notifikasi perangkat tidak didukung di browser ini.',
                'Buka LRS lewat Chrome/Edge/Safari versi terbaru pada koneksi HTTPS.');
            return Promise.resolve();
        }

        if (Notification.permission === 'denied') {
            renderUnavailable('Notifikasi diblokir untuk situs ini.',
                'Izinkan notifikasi lewat pengaturan situs di browser, lalu muat ulang halaman.');
            return Promise.resolve();
        }

        return navigator.serviceWorker.ready.then(function (registration) {
            return registration.pushManager.getSubscription().then(function (subscription) {
                if (!subscription) {
                    render('inactive', 'Belum aktif di perangkat ini.', 'enable');
                    return;
                }

                render('active', 'Aktif di perangkat ini.', 'disable');

                // Izin sudah diberikan & subscription ada, tetapi server belum
                // tahu (perangkat baru, ganti akun, atau browser merotasi
                // endpoint). Sinkronkan diam-diam — TIDAK memunculkan prompt.
                if (!alreadySynced(subscription.endpoint)) {
                    return syncToServer(subscription).catch(function () {
                        render('inactive', 'Belum aktif di perangkat ini.', 'enable');
                    });
                }

                return undefined;
            });
        }).catch(function () {
            renderUnavailable('Status notifikasi perangkat tidak bisa dibaca.',
                'Muat ulang halaman untuk mencoba lagi.');
        });
    }

    function enable() {
        toggleBtn.disabled = true;
        setHint('');

        // Prompt izin HANYA di sini — dipicu klik user, bukan saat page load.
        Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                renderUnavailable(
                    permission === 'denied'
                        ? 'Notifikasi diblokir untuk situs ini.'
                        : 'Izin notifikasi tidak diberikan.',
                    'Tanpa izin, perangkat ini tidak bisa menerima notifikasi. '
                        + 'Notifikasi tetap tersimpan di daftar notifikasi aplikasi.'
                );
                return null;
            }

            return navigator.serviceWorker.ready
                .then(ensureSubscription)
                .then(syncToServer)
                .then(function () {
                    render('active', 'Aktif di perangkat ini.', 'disable');
                    setHint('Perangkat ini akan menerima notifikasi LRS.');
                })
                .catch(function (error) {
                    render('inactive', 'Gagal mengaktifkan notifikasi perangkat.', 'enable');
                    setHint('Coba lagi. Bila terus gagal, muat ulang halaman.');
                    if (window.console) console.warn('[LRS push]', error && error.message);
                });
        });
    }

    function disable() {
        toggleBtn.disabled = true;
        setHint('');

        navigator.serviceWorker.ready
            .then(function (registration) { return registration.pushManager.getSubscription(); })
            .then(function (subscription) {
                if (!subscription) return null;
                var endpoint = subscription.endpoint;
                // Lepas di browser dulu, lalu beri tahu server. Server juga
                // membuang endpoint ini saat push berikutnya gagal (410/404).
                return subscription.unsubscribe()
                    .then(function () { return post(UNSUBSCRIBE_URL, 'DELETE', { endpoint: endpoint }); })
                    .catch(function () { /* sudah lepas di sisi browser */ });
            })
            .then(function () {
                clearSynced();
                render('inactive', 'Belum aktif di perangkat ini.', 'enable');
                setHint('Perangkat ini tidak lagi menerima notifikasi.');
            })
            .catch(function (error) {
                render('active', 'Aktif di perangkat ini.', 'disable');
                setHint('Gagal menonaktifkan. Coba lagi.');
                if (window.console) console.warn('[LRS push]', error && error.message);
            });
    }

    toggleBtn.addEventListener('click', function () {
        if (toggleBtn.dataset.action === 'disable') {
            disable();
        } else {
            enable();
        }
    });

    // Status awal: hanya membaca, tidak pernah meminta izin.
    refresh();
})();
</script>
@endif
