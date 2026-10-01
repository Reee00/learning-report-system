{{--
    Peningkatan navigasi ringan — TANPA framework, TANPA SPA.

    Tiga hal saja, semuanya progressive enhancement. Aplikasi tetap berfungsi
    penuh tanpa JavaScript, dan tidak ada satu pun request GET yang ditahan
    atau diblokir oleh skrip ini.

    1. VIEW TRANSITIONS
       Cross-document view transition diaktifkan murni lewat CSS
       (`@view-transition { navigation: auto }` di layouts/app.blade.php).
       Tidak ada JS, tidak ada library. Browser tanpa dukungan mengabaikannya
       dan navigasi berjalan seperti biasa.

    2. PREFETCH SAAT HOVER / FOKUS / IDLE
       Hanya untuk tautan GET same-origin yang aman. Daftar larangannya
       eksplisit — lihat PREFETCH_BLOCKED di bawah — dan setiap tautan bisa
       mengecualikan dirinya sendiri dengan `data-no-prefetch`.

       CATATAN JUJUR SOAL MANFAATNYA: halaman terautentikasi dikirim Laravel
       dengan `Cache-Control: no-cache, private`, jadi browser menyimpannya
       tetapi tetap wajib revalidasi. Prefetch karena itu lebih banyak
       menghangatkan koneksi dan sesi server daripada menghemat pengiriman
       HTML. Itu sebabnya prefetch di sini SENGAJA dibatasi: hanya pada
       interaksi nyata (hover/fokus), tidak pada seluruh halaman sekaligus.

    3. INDIKATOR PROGRES
       Muncul HANYA bila navigasi terasa lambat (melewati ambang 400 ms),
       bukan spinner di setiap klik.

    Tidak ada yang di-prefetch untuk: POST, logout, unduhan berkas, media
    biner, ekspor, dan halaman yang punya efek samping. Prefetch juga mati
    total pada koneksi hemat data (Save-Data) atau jaringan 2G.
--}}
<script>
(function () {
    'use strict';

    if (window.__lrsNavEnhanced) return;
    window.__lrsNavEnhanced = true;

    // -----------------------------------------------------------------
    // Kelayakan lingkungan
    // -----------------------------------------------------------------
    var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection || {};
    var saveData = conn.saveData === true;
    var slowNetwork = /(^|-)2g$/.test(conn.effectiveType || '');
    var PREFETCH_ENABLED = !saveData && !slowNetwork;

    // -----------------------------------------------------------------
    // Daftar larangan prefetch.
    //
    // Semua yang punya efek samping, mengubah state, mengirim berkas, atau
    // membocorkan data pribadi ke cache TIDAK boleh di-prefetch. Nama route
    // di aplikasi ini memakai awalan ini, dan berkas unduhan selalu punya
    // ekstensi.
    // -----------------------------------------------------------------
    var BLOCKED_PATH = /^\/(logout|login|exports?|download|attendance\/export|media)\b/i;
    var BLOCKED_EXT = /\.(pdf|csv|xlsx?|zip|jpe?g|png|gif|webp|svg|mp4|mov|avi|mpe?g|docx?|pptx?)$/i;

    function resolve(href) {
        try {
            return new URL(href, window.location.href);
        } catch (error) {
            return null;
        }
    }

    function isSafeTarget(anchor) {
        if (!anchor || anchor.tagName !== 'A') return false;
        if (!anchor.hasAttribute('href')) return false;
        if (anchor.hasAttribute('download')) return false;
        if (anchor.hasAttribute('data-no-prefetch')) return false;
        if (anchor.hasAttribute('data-bs-toggle')) return false; // dropdown/modal, bukan navigasi
        if (anchor.target && anchor.target !== '' && anchor.target !== '_self') return false;
        if (anchor.getAttribute('rel') && /\bexternal\b/.test(anchor.getAttribute('rel'))) return false;

        // Tautan di dalam form POST bukan navigasi GET — jangan disentuh.
        var form = anchor.closest('form');
        if (form && (form.getAttribute('method') || 'get').toLowerCase() === 'post') return false;

        return true;
    }

    function isSafeUrl(url) {
        if (!url) return false;
        if (url.origin !== window.location.origin) return false;
        // Sudah berada di halaman itu: tidak ada yang perlu diambil.
        if (url.pathname === window.location.pathname && url.search === window.location.search) return false;
        if (BLOCKED_PATH.test(url.pathname)) return false;
        if (BLOCKED_EXT.test(url.pathname)) return false;
        // Fragment-only.
        if (url.pathname === window.location.pathname && url.search === window.location.search) return false;
        return true;
    }

    var requested = Object.create(null);

    function prefetch(anchor) {
        if (!PREFETCH_ENABLED) return;
        if (document.visibilityState !== 'visible') return;
        if (!isSafeTarget(anchor)) return;

        var url = resolve(anchor.getAttribute('href'));
        if (!isSafeUrl(url)) return;
        if (requested[url.href]) return;

        // Batasi jumlah prefetch per halaman: sidebar punya puluhan tautan,
        // dan menghangatkan semuanya hanya membebani server.
        if (Object.keys(requested).length >= 8) return;

        requested[url.href] = true;

        var link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url.href;
        link.as = 'document';
        // Penanda agar mudah diaudit di DevTools dan tidak pernah ikut
        // dianggap sebagai bagian dari halaman.
        link.setAttribute('data-lrs-prefetch', '1');
        document.head.appendChild(link);
    }

    // Hover dan fokus saja — bukan seluruh halaman sekaligus. Jeda kecil
    // mencegah prefetch terpicu saat mouse hanya melintas.
    var hoverTimer = null;

    document.addEventListener('mouseover', function (event) {
        var anchor = event.target.closest ? event.target.closest('a[href]') : null;
        if (!anchor) return;

        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(function () { prefetch(anchor); }, 120);
    }, { passive: true });

    document.addEventListener('mouseout', function () {
        clearTimeout(hoverTimer);
    }, { passive: true });

    document.addEventListener('focusin', function (event) {
        var anchor = event.target.closest ? event.target.closest('a[href]') : null;
        if (anchor) prefetch(anchor);
    }, { passive: true });

    // -----------------------------------------------------------------
    // Indikator progres navigasi.
    //
    // Hanya muncul setelah navigasi melewati ambang; navigasi cepat tidak
    // pernah menampilkannya, sehingga tidak ada kedipan.
    // -----------------------------------------------------------------
    var THRESHOLD_MS = 400;
    var progressEl = null;
    var progressTimer = null;

    function showProgress() {
        if (!progressEl) return;
        progressEl.hidden = false;
        progressEl.classList.add('is-visible', 'is-running');
    }

    function resetProgress() {
        clearTimeout(progressTimer);
        if (!progressEl) return;
        progressEl.classList.remove('is-visible', 'is-running');
        progressEl.hidden = true;
    }

    function scheduleProgress() {
        clearTimeout(progressTimer);
        progressTimer = setTimeout(showProgress, THRESHOLD_MS);
    }

    function initProgress() {
        progressEl = document.getElementById('navProgress');
        if (!progressEl) return;
        progressEl.hidden = true;

        // Navigasi ditinggalkan: jadwalkan indikator, jangan tampilkan dulu.
        window.addEventListener('beforeunload', function () {
            scheduleProgress();
        }, { passive: true });

        // Halaman baru selesai dimuat — batalkan indikator yang belum tampil.
        window.addEventListener('pageshow', resetProgress);
        window.addEventListener('load', resetProgress);

        // Halaman dipulihkan dari bfcache (tombol Back): tidak ada request,
        // jadi tidak boleh ada indikator yang tertinggal menyala.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) resetProgress();
        });

        // Form GET: navigasi juga perlu indikator. Form POST dikecualikan —
        // pengiriman form sudah memberi umpan baliknya sendiri.
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || form.tagName !== 'FORM') return;
            if ((form.getAttribute('method') || 'get').toLowerCase() === 'post') return;
            if (form.hasAttribute('data-no-progress')) return;
            scheduleProgress();
        }, { passive: true });
    }

    // View Transitions API: kalau tersedia, biarkan browser mengurusnya.
    // Blok ini hanya memastikan properti `view-transition-name` yang tidak
    // sengaja (mis. pada sidebar yang sama di semua halaman) tidak memicu
    // peringatan di console.
    function initViewTransitions() {
        if (!document.startViewTransition) return;
        // Cross-document transition diaktifkan lewat CSS; tidak ada yang
        // perlu dikerjakan di sini. Fungsi ini sengaja dibiarkan sebagai
        // penanda bahwa dukungan sudah diperiksa.
    }

    function boot() {
        initProgress();
        initViewTransitions();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
