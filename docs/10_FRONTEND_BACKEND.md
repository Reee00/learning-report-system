# Frontend dan Backend

## Frontend
Blade views memakai Bootstrap 5.3 dan Bootstrap Icons melalui CDN. Layout utama adalah left sidebar application layout dengan top bar, drawer/overlay untuk mobile, Bootstrap grid, responsive table, dan media queries. UI memiliki view admin compatibility, coach, PIC, attendance, student, report, dan media. Tidak ada bukti PWA atau klaim full responsive yang lebih luas dari implementasi ini.

### Komponen UI bersama (2026-09-24)
Beberapa tampilan diperbaiki pada satu komponen bersama, bukan per halaman:

- **Paginasi** — `AppServiceProvider::boot()` memanggil `Paginator::useBootstrapFive()` karena default Laravel (`pagination::tailwind`) menghasilkan markup Tailwind di aplikasi Bootstrap 5. View `resources/views/vendor/pagination/bootstrap-5.blade.php` menggantikan view bawaan untuk seluruh pemanggil `->links()` (15 view) dengan label Indonesia eksplisit (Sebelumnya / Berikutnya / Menampilkan … dari … data), ikon Bootstrap Icons, dan status aktif/disabled yang jelas. View ini sengaja tidak memakai `@lang()`/`__()`: repositori tidak punya folder `lang/`, sehingga key seperti `pagination.previous` dulu tercetak mentah di halaman.
- **Angka tidak terpecah** — `resources/views/layouts/app.blade.php` memakai `overflow-wrap: break-word; word-break: normal` untuk sel tabel dan `white-space: nowrap` untuk badge. `overflow-wrap: anywhere` menciutkan min-content sel menjadi satu karakter sehingga "10" tampil sebagai "1" di baris pertama dan "0" di baris berikutnya.
- **Form Tambah/Edit Sekolah** — modal `modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down`; isi terbagi dua kolom `col-12 col-lg-6` (Informasi Sekolah | Setup Awal) yang menumpuk satu kolom di tablet dan ponsel; footer `d-grid gap-2 d-sm-flex justify-content-sm-end` membuat tombol selebar penuh di layar kecil. Setiap label memakai `for`/`id` eksplisit, dan modal dibuka kembali saat validasi gagal agar old input terlihat.
- **Form pola jadwal** — `resources/views/admin/schedules/bulk.blade.php` memakai pola berulang (template `<template>` + klon DOM) untuk blok sekolah dan baris kelas, sehingga field tingkat sekolah (termasuk `Tanggal Mulai` dan `Jumlah Pertemuan` yang melekat pada blok) tidak diulang per baris dan indeks `name` dibangun ulang dari posisi DOM sebelum submit. Kunci array hari (`days[1]`..`days[7]`) adalah nomor hari ISO, jadi tidak ada field hari tersembunyi yang bisa tidak sinkron.
- **Daftar jadwal** — `resources/views/admin/schedules/index.blade.php` punya dua tab: POLA per hari (default SENIN, mengikuti alur Excel) dan SESI tergenerate (filter tanggal/jam). Detail satu pola ada di `admin/schedules/pattern.blade.php`.
- **Nomor baris tabel** melanjutkan antar halaman: `($paginator->currentPage() - 1) * perPage() + $loop->iteration`.

## Backend
Route -> middleware -> controller -> service/model -> Blade. `AuthorizationService` memeriksa capability dan scope; `AttendanceScopeService` memfilter report attendance; `MediaStorageService` memakai Laravel Filesystem. `resources/css` dan `resources/js` tersedia, tetapi layout utama tidak menggunakan `@vite`; `npm run build` saat ini no-op.

Role yang tidak punya portal khusus memakai view bersama sesuai capability. Keamanan tidak boleh bergantung pada tombol yang disembunyikan di UI.
