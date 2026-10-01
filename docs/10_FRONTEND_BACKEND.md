# Frontend dan Backend

Disinkronkan dengan kode: **2026-09-28**.

## Frontend

Blade views memakai Bootstrap 5.3 dan Bootstrap Icons melalui **CDN**. Layout utama adalah left sidebar dengan top bar, drawer/overlay untuk mobile, Bootstrap grid, tabel responsif, dan media query.

`resources/css` dan `resources/js` tersedia, tetapi layout utama **tidak** memakai `@vite`, dan `npm run build` adalah no-op. Jangan mengasumsikan ada langkah build aset.

### Komponen UI Bersama

Perbaikan dilakukan pada satu komponen bersama, bukan per halaman:

**Paginasi.** `AppServiceProvider::boot()` memanggil `Paginator::useBootstrapFive()` karena default Laravel (`pagination::tailwind`) menghasilkan markup Tailwind di aplikasi Bootstrap 5. View `resources/views/vendor/pagination/bootstrap-5.blade.php` menggantikan view bawaan untuk **seluruh** pemanggil `->links()`, dengan label Indonesia eksplisit (Sebelumnya / Berikutnya / Menampilkan … dari … data), ikon Bootstrap Icons, dan status aktif/disabled yang jelas. View ini sengaja **tidak** memakai `@lang()`/`__()`: repositori tidak punya folder `lang/`, sehingga key seperti `pagination.previous` dulu tercetak mentah di halaman.

**Angka tidak terpecah.** `resources/views/layouts/app.blade.php` memakai `overflow-wrap: break-word; word-break: normal` untuk sel tabel dan `white-space: nowrap` untuk badge. `overflow-wrap: anywhere` menciutkan min-content sel menjadi satu karakter, sehingga "10" tampil sebagai "1" di baris pertama dan "0" di baris berikutnya.

**Form Tambah/Edit Sekolah.** Modal `modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down`; isi terbagi dua kolom `col-12 col-lg-6` (Informasi Sekolah | Setup Awal) yang menumpuk di tablet dan ponsel; footer `d-grid gap-2 d-sm-flex justify-content-sm-end` membuat tombol selebar penuh di layar kecil. Setiap label memakai `for`/`id` eksplisit, dan modal dibuka kembali saat validasi gagal agar isian lama terlihat.

**Form pola jadwal.** `admin/schedules/bulk.blade.php` memakai pola berulang (template `<template>` + klon DOM) untuk blok sekolah dan baris kelas, sehingga field tingkat sekolah (termasuk `Tanggal Mulai` dan `Jumlah Pertemuan` yang melekat pada blok) tidak diulang per baris dan indeks `name` dibangun ulang dari posisi DOM sebelum submit. Kunci array hari (`days[1]`..`days[7]`) adalah nomor hari ISO, jadi tidak ada field hari tersembunyi yang bisa tidak sinkron.

**Daftar jadwal.** `admin/schedules/index.blade.php` punya dua tab: POLA per hari (default SENIN) dan SESI tergenerate (filter tanggal/jam). Detail satu pola ada di `admin/schedules/pattern.blade.php`.

**Nomor baris tabel** melanjutkan antar halaman: `($paginator->currentPage() - 1) * perPage() + $loop->iteration`.

### PWA di Sisi Klien

`partials/pwa-head.blade.php` dan `partials/pwa-scripts.blade.php` mendaftarkan service worker pada event `load` dengan `updateViaCache: 'none'`, memperbarui registrasi tiap jam, menyimpan penolakan prompt pemasangan di localStorage, dan mengirim pesan `LRS_CLEAR_CACHES` saat logout.

`partials/push-notifications.blade.php` merender tombol aktivasi **hanya** bila `VAPID_PUBLIC_KEY` terisi, dan meminta izin notifikasi hanya pada klik eksplisit pengguna. Detail: [modules/pwa.md](modules/pwa.md), [modules/web-push.md](modules/web-push.md).

### Dua Partial Bersama Lainnya

- `partials/whatsapp-link.blade.php` — satu tempat untuk ketiga keadaan nomor WhatsApp (tautan `wa.me`, nomor tanpa tautan, "Belum diatur"). Dipakai daftar coach, detail coach, dan kartu ringkasan Account Settings supaya markupnya tidak pernah berbeda antar halaman. Partial ini hanya merender; kewenangan tetap diputuskan pemanggil. Detail: [modules/accounts.md](modules/accounts.md).
- `partials/password-toggle-scripts.blade.php` — skrip tombol tampil/sembunyikan password, dipakai halaman login dan kartu Ganti Password. Memakai listener `click` terdelegasi dengan penjaga idempotensi, jadi boleh di-include berkali-kali; nilainya tidak pernah ditulis ulang, hanya `input.type` yang berganti.

Keduanya mengikuti pola partial PWA di atas: satu berkas, di-include di beberapa halaman, tanpa duplikasi markup atau skrip.

## Backend

```
Route → middleware → controller → service/model → Blade
```

- `AuthorizationService` — capability dan scope.
- `AttendanceScopeService` — memfilter kehadiran pada laporan induk.
- `AttendanceExportService` — CSV/Excel/PDF.
- `MediaStorageService` — abstraksi Filesystem.
- `ScheduleTemplateService` / `TeachingScheduleImportService` — pola, generate, impor.
- `CustomNotificationService` / `ReportReminderService` — notifikasi dan pengingat.
- `ActivityLogService` — satu-satunya jalur tulis activity log.

Peran yang tidak punya portal khusus memakai view bersama sesuai capability.

## Alur Asinkron

Notifikasi keluar lewat kanal `database` dan `WebPushChannel`; push dikirim job `SendWebPush` oleh queue worker. Aplikasi **memerlukan worker** agar notifikasi dan push benar-benar terkirim. Lihat [operations/queue-worker.md](operations/queue-worker.md).

## Aturan

**Keamanan tidak boleh bergantung pada tombol yang disembunyikan di UI.** Setiap elemen yang tidak dirender harus punya pasangan penolakan di server. Scope selalu diperiksa di backend; filter dari klien hanya mempersempit.

> Pernyataan lama "tidak ada bukti PWA" **tidak berlaku lagi**.
