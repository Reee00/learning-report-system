# Arsitektur Sistem

## Bentuk Aplikasi
Laravel MVC dengan Blade, Eloquent, middleware, dan service layer. Route web berada di `routes/web.php`; tidak ada `routes/api.php`.

## Komponen Pengendali
- `AuthorizationService`: role capability dan school/class access.
- `AttendanceScopeService`: query attendance berdasarkan role, assignment, status, dan filter.
- `MediaStorageService`: penyimpanan dan penghapusan media melalui Laravel Filesystem.
- Controller namespace `Admin` adalah namespace historis/kompatibilitas untuk route `/admin/*`, bukan role `admin`.

## Infrastruktur Runtime
PHP `^8.4`, Laravel `^12.0`, MySQL sebagai target production, Bootstrap 5.3/Icons CDN, dan FastExcel/Dompdf. `config/filesystems.php` default report media ke local private disk. Nilai `.env` aktif: `Need Verification`.

## Alur Request
Browser -> auth/role/permission middleware -> controller -> authorization/scope service -> Eloquent model/database -> Blade response. Media report disajikan melalui controller terotorisasi `/media/{media}`, bukan akses publik langsung.
