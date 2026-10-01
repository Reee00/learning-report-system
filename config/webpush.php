<?php

use App\Models\PushSubscription;

/**
 * Konfigurasi Web Push (PWA Phase 2).
 *
 * KEAMANAN — VAPID private key:
 *   - Hanya dibaca dari .env (VAPID_PRIVATE_KEY) dan TIDAK PERNAH dikirim ke
 *     klien, tidak masuk manifest, service worker, atau payload push.
 *   - Hanya `public_key` yang boleh sampai ke browser (dipakai sebagai
 *     applicationServerKey saat subscribe).
 *   - Jangan isi nilai asli di .env.example atau berkas yang di-commit.
 *
 * Bila public_key/private_key kosong, fitur push dinonaktifkan sepenuhnya:
 * kanal push menjadi no-op dan notifikasi database tetap berjalan normal.
 */
return [

    /** Kunci autentikasi VAPID. Generate: php artisan webpush:vapid */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    /** Model subscription milik aplikasi (turunan model paket). */
    'model' => PushSubscription::class,

    /** Tabel subscription, dibuat oleh migrasi create_push_subscriptions_table. */
    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),

    /** Koneksi database yang dipakai model & migrasi. */
    'database_connection' => env('WEBPUSH_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    /**
     * Opsi HTTP client. Timeout pendek disengaja: pengiriman push berjalan di
     * dalam job antrean (App\Jobs\SendWebPush), jadi endpoint push yang lambat
     * hanya menahan worker — bukan request pembuat notifikasi. Batas ini juga
     * yang menjaga satu job tidak menggantung lebih lama dari `timeout` job.
     */
    'client_options' => [
        'timeout' => (int) env('WEBPUSH_TIMEOUT', 5),
        'connect_timeout' => (int) env('WEBPUSH_CONNECT_TIMEOUT', 5),
    ],

    /**
     * Padding otomatis Minishlink\WebPush. Biarkan true; set false hanya bila
     * perlu mendukung Firefox Android dengan endpoint v1.
     */
    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

];
