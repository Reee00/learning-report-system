# Frontend dan Backend

## Frontend
Blade views memakai Bootstrap 5.3 dan Bootstrap Icons melalui CDN. Layout utama adalah left sidebar application layout dengan top bar, drawer/overlay untuk mobile, Bootstrap grid, responsive table, dan media queries. UI memiliki view admin compatibility, coach, PIC, attendance, student, report, dan media. Tidak ada bukti PWA atau klaim full responsive yang lebih luas dari implementasi ini.

## Backend
Route -> middleware -> controller -> service/model -> Blade. `AuthorizationService` memeriksa capability dan scope; `AttendanceScopeService` memfilter report attendance; `MediaStorageService` memakai Laravel Filesystem. `resources/css` dan `resources/js` tersedia, tetapi layout utama tidak menggunakan `@vite`; `npm run build` saat ini no-op.

Role yang tidak punya portal khusus memakai view bersama sesuai capability. Keamanan tidak boleh bergantung pada tombol yang disembunyikan di UI.
