{{--
    PWA Phase 1 — registrasi Service Worker & perilaku "Pasang Aplikasi".

    Catatan keamanan: script ini tidak pernah mengirim data pengguna ke service
    worker. Satu-satunya pesan yang dikirim adalah permintaan membersihkan cache
    runtime saat logout, supaya tidak ada sisa aset/data di perangkat.
--}}
<script>
(function () {
    'use strict';

    var INSTALL_DISMISSED_KEY = 'lrs_pwa_install_dismissed';

    // Hanya jalan pada secure context (HTTPS atau localhost). Di http:// biasa
    // service worker memang tidak diizinkan browser — diam saja, aplikasi tetap
    // berfungsi normal tanpa PWA.
    var supportsServiceWorker = 'serviceWorker' in navigator && window.isSecureContext === true;

    function isStandalone() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
            || window.navigator.standalone === true;
    }

    /* ---------------- Service Worker registration ---------------- */
    if (supportsServiceWorker) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', {
                scope: '/',
                // Selalu ambil sw.js dari jaringan: update tidak boleh tertahan
                // cache HTTP.
                updateViaCache: 'none'
            }).then(function (registration) {
                // Cek versi baru setiap aplikasi dibuka.
                registration.update().catch(function () {});
                // Dan sekali sejam untuk sesi yang dibiarkan terbuka lama.
                window.setInterval(function () {
                    registration.update().catch(function () {});
                }, 60 * 60 * 1000);
            }).catch(function (error) {
                console.warn('[LRS PWA] Registrasi service worker gagal:', error);
            });
        });

        // Logout: minta service worker membuang cache runtime. Halaman
        // terautentikasi memang tidak pernah di-cache, ini sabuk pengaman kedua.
        document.addEventListener('submit', function (event) {
            var form = event.target;
            var action = form && form.getAttribute ? (form.getAttribute('action') || '') : '';
            if (action.indexOf('/logout') === -1) return;
            var controller = navigator.serviceWorker.controller;
            if (controller) {
                controller.postMessage({ type: 'LRS_CLEAR_CACHES' });
            }
        }, true);
    }

    /* ---------------- "Pasang Aplikasi" (install prompt) ---------------- */
    var installButton = document.getElementById('pwaInstallBtn');
    var deferredPrompt = null;

    function hideInstallButton() {
        if (installButton) installButton.classList.add('d-none');
    }

    if (installButton && !isStandalone() && window.localStorage.getItem(INSTALL_DISMISSED_KEY) !== '1') {
        window.addEventListener('beforeinstallprompt', function (event) {
            // Tahan prompt bawaan browser; tampilkan lewat tombol agar tidak
            // mengganggu alur kerja.
            event.preventDefault();
            deferredPrompt = event;
            installButton.classList.remove('d-none');
        });

        installButton.addEventListener('click', function () {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function (choice) {
                if (choice && choice.outcome === 'dismissed') {
                    window.localStorage.setItem(INSTALL_DISMISSED_KEY, '1');
                }
                deferredPrompt = null;
                hideInstallButton();
            }).catch(function () {
                deferredPrompt = null;
                hideInstallButton();
            });
        });

        window.addEventListener('appinstalled', function () {
            deferredPrompt = null;
            hideInstallButton();
        });
    } else {
        hideInstallButton();
    }
})();
</script>
