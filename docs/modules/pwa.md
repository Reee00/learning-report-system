# Modul: PWA (Progressive Web App)

Sinkron dengan kode: **2026-09-28**. Sumber: `public/manifest.json`, `public/sw.js`, `public/offline.html`, `public/icons/`, `resources/views/partials/pwa-head.blade.php`, `resources/views/partials/pwa-scripts.blade.php`, `tests/JavaScript/service-worker-routing.test.js`.

> Aplikasi ini **sudah** merupakan PWA. Setiap pernyataan lama yang berbunyi "belum ada PWA" atau "belum ada service worker" tidak berlaku lagi.

## Berkas

| Berkas | Isi |
|---|---|
| `public/manifest.json` | Manifest aplikasi |
| `public/sw.js` | Service worker |
| `public/offline.html` | Halaman cadangan saat offline |
| `public/icons/` | Ikon 192 & 512, varian `any` dan `maskable` |

Ekstensi sengaja **`.json`**, bukan `.webmanifest`, karena konfigurasi MIME server (nginx) tidak selalu mengenali `.webmanifest`. Jangan menggantinya tanpa memastikan MIME `application/manifest+json` terdaftar.

## Manifest

| Properti | Nilai |
|---|---|
| `id` / `name` | `/` / Learning Report System |
| `short_name` | LRS |
| `lang` | `id` |
| `start_url` | `/` |
| `display` | `standalone` |
| `theme_color` | `#4f46e5` |
| `background_color` | `#f4f7f9` |
| Ikon | 192 & 512, `any` + `maskable` |
| Shortcuts | **Buat Laporan** (`/coach/reports/create`), **Laporan Saya** (`/coach/reports`), **Kehadiran** (`/attendance`) |

## Service worker (`public/sw.js`)

`SW_VERSION = 'v1.1.0'` menentukan nama cache: `lrs-static-${SW_VERSION}` dan `lrs-runtime-${SW_VERSION}`. **Menaikkan versi ini adalah cara resmi menginvalidasi cache** — jangan mengubah isi cache secara manual.

Handler:

| Event | Perilaku |
|---|---|
| `install` | Precache aset inti |
| `activate` | Hapus seluruh cache lama berprefiks `lrs-*`, `clients.claim()`, kirim pesan `LRS_SW_ACTIVATED` ke klien |
| `fetch` | Lihat strategi di bawah |
| `message` | Menangani `LRS_CLEAR_CACHES` (dikirim saat logout) |
| `push` | Menampilkan notifikasi dari payload |
| `notificationclick` | Membuka `url` aman dari payload |

Helper: `handleNavigation()`, `staleWhileRevalidate()`, `storeInCache()`, `readPushPayload()`, `safeTargetUrl()`, `safeIconUrl()`, serta predikat `IS_PROTECTED_PATH` / `IS_STATIC_ASSET`.

### Strategi cache

| Jenis permintaan | Strategi |
|---|---|
| Navigasi halaman | **Network-only**. Tidak ada HTML terautentikasi yang disimpan; bila jaringan gagal, yang disajikan adalah `offline.html` |
| Path terproteksi | **Passthrough** — tidak pernah menyentuh cache |
| Aset statis & CDN dalam allowlist | **Stale-while-revalidate** |

Konsekuensi yang disengaja: **tidak ada data pengguna yang tersimpan di Cache Storage**. Halaman yang butuh login tidak akan pernah tampil dari cache, sehingga tidak ada kebocoran antar-pengguna pada perangkat bersama. Handler `push` juga tidak menulis apa pun ke Cache Storage.

## Pemasangan di sisi klien

`pwa-head.blade.php` dan `pwa-scripts.blade.php`:

- Service worker didaftarkan pada event `load` dengan `updateViaCache: 'none'`.
- `registration.update()` dipanggil saat halaman dibuka, lalu diulang tiap satu jam — mempercepat penerimaan versi baru.
- Prompt pemasangan dapat ditutup; penolakan disimpan di kunci localStorage `lrs_pwa_install_dismissed`.
- Saat logout, klien mengirim pesan `LRS_CLEAR_CACHES` agar cache `lrs-*` dibersihkan.
- Seluruh skrip hanya berjalan pada **secure context** (HTTPS atau localhost).

## Menerapkan perubahan

Setelah mengubah berkas di `public/sw.js` yang memengaruhi cache:

1. Naikkan `SW_VERSION`.
2. Jalankan `npm run test:pwa`.
3. Muat ulang aplikasi; pengguna akan mendapat versi baru pada pembukaan berikutnya.

## Test

```
npm run test:pwa
```

→ `node tests/JavaScript/service-worker-routing.test.js public/sw.js` — **37 pemeriksaan**, memverifikasi pemilihan strategi per jenis permintaan, penanganan push, dan sanitasi URL. Jalankan setiap kali `public/sw.js` disentuh.

Test sisi server: `PwaTest`.

Lihat juga [web-push.md](web-push.md) untuk sisi pengiriman notifikasi.
