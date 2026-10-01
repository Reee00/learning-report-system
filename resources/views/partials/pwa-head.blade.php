{{--
    PWA Phase 1 — tag <head> bersama untuk semua halaman LRS (app shell & login).

    Hanya metadata publik: manifest, ikon, dan safe-area viewport.
    Tidak ada token, kunci, atau data pengguna yang ditulis di sini.
    Path ditulis root-relative (bukan asset()) supaya manifest selalu
    same-origin dengan halaman, apa pun isi APP_URL.
--}}
<meta name="theme-color" content="#4f46e5">
<meta name="color-scheme" content="light">

{{-- Nama berkas sengaja .json, bukan .webmanifest: nginx (Herd & container
     produksi) mengirim .webmanifest sebagai application/octet-stream sehingga
     browser menolak memasang aplikasi. .json sudah dikenali semua web server. --}}
<link rel="manifest" href="/manifest.json">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">

{{-- Installable / standalone behavior (Chrome, Safari/iOS, Samsung Internet). --}}
<meta name="application-name" content="LRS">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="LRS">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="format-detection" content="telephone=no">
