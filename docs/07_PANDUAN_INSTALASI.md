# Panduan Instalasi

## Prasyarat
PHP 8.4 atau kompatibel dengan `composer.json`, Composer, Node/NPM bila asset perlu diperiksa, dan database MySQL untuk deployment yang ditargetkan. SQLite dapat dipakai bila `DB_CONNECTION` tidak diubah, karena itu adalah default repository.

## Setup
```text
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

`composer setup` menjalankan rangkaian setup tersebut dengan migrate `--force`. Script build saat ini adalah no-op (`echo 'No build step required'`).

## Environment
Set `APP_*`, `DB_*`, `SESSION_*`, `FILESYSTEM_DISK`, dan `REPORT_MEDIA_DISK` sesuai environment. Untuk target production gunakan MySQL dan review credential. `.env` tidak tersedia di repository audit ini, sehingga nilai aktif berstatus `Need Verification`.

Report media default berada pada disk `report_media` di `storage/app/report-media`, bukan pada public symlink. Pastikan direktori storage writable. Cloudinary env hanya relevan untuk legacy helper/migration dan bukan jalur upload baru.
