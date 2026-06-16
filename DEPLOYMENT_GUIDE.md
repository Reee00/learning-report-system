# Deployment Guide: Learning Report System

Dokumentasi ini menjelaskan cara deploy aplikasi Learning Report System untuk environment produksi atau staging.

## 1. Ringkasan
Aplikasi menggunakan Laravel dan dapat dijalankan:
- dengan Docker
- di platform PaaS seperti Railway
- secara manual pada server Linux/VM

Aplikasi ini juga menggunakan Cloudinary untuk upload media dan database untuk session/cache/queue.

## 2. Persyaratan
- PHP 8.3
- Composer
- MySQL atau PostgreSQL
- Web server (Nginx / Apache) bila menggunakan deployment manual
- Docker jika deploy lewat container
- Cloudinary account untuk upload foto / video

## 3. Environment Variables
Salin `railway.env.example` ke `.env` dan isi nilai-nilai berikut:

- `APP_NAME`
- `APP_ENV=production`
- `APP_KEY` (generate dengan `php artisan key:generate`)
- `APP_DEBUG=false`
- `APP_URL` URL aplikasi

- `DB_CONNECTION` (mysql / pgsql)
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database`

- `FILESYSTEM_DISK=public`

- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`

## 4. Deployment dengan Docker
Aplikasi sudah menyediakan `Dockerfile`.

### 4.1 Build image
```bash
docker build -t learning-report-system .
```

### 4.2 Jalankan container
```bash
docker run -d \
  --name learning-report-system \
  -p 8080:8080 \
  --env-file .env \
  learning-report-system
```

### 4.3 Catatan penting Dockerfile
Dockerfile melakukan:
- install ekstensi PHP yang dibutuhkan (`pdo_mysql`, `pdo_pgsql`, `mbstring`, `gd`, dll.)
- install dependencies Composer tanpa dev
- jalankan `composer run-script post-autoload-dump`
- buat permission untuk `storage` dan `bootstrap/cache`
- expose port `8080`
- menjalankan perintah:
  - `php artisan config:clear`
  - `php artisan migrate --force`
  - `php artisan db:seed --force`
  - `php artisan storage:link`
  - `php artisan serve --host=0.0.0.0 --port=$PORT`

> Jika kamu ingin menjalankan aplikasi dalam mode sebenarnya pada server produksi, sebaiknya gunakan web server seperti Nginx / Apache bukan `php artisan serve`.

## 5. Deployment di Railway
Platform Railway mendukung deploy container dan environment variable.

### 5.1 Siapkan repo
1. Hubungkan repository ke Railway.
2. Pilih deployment Docker.
3. Tambahkan environment variable sesuai `railway.env.example`.
4. Pastikan `PORT` diset, biasanya `8080`.

### 5.2 Pengaturan database
Railway menyediakan database terkelola.
- Set `DB_CONNECTION` ke `mysql`
- Isi `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### 5.3 Cloudinary
Tambahkan variabel Cloudinary:
- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`

### 5.4 Jalankan deploy
Railway akan membangun image berdasarkan `Dockerfile`.

> Pastikan migrasi dan seeding otomatis diterima. Dockerfile sudah memanggil `php artisan migrate --force` dan `php artisan db:seed --force`.

## 6. Deployment Manual (Server Linux)
Jika deploy tanpa Docker, ikuti langkah berikut:

1. Pastikan PHP 8.3, Composer, dan ekstensi PHP terpasang.
2. Clone repository ke server.
3. Salin `.env.example` menjadi `.env` dan isi environment variables.
4. Jalankan:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. Pastikan direktori `storage` dan `bootstrap/cache` writable oleh web server user:
   ```bash
   chown -R www-data:www-data storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```
6. Konfigurasi web server:
   - Nginx root ke folder `public`
   - Izinkan `index.php`
   - Atur proxy pass atau fastcgi ke PHP-FPM

Contoh ringkas Nginx:
```nginx
server {
    listen 80;
    server_name example.com;
    root /path/to/learning-report-system/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 7. Storage dan File Upload
- `FILESYSTEM_DISK=public`
- Jalankan `php artisan storage:link` untuk membuat symbolic link ke `public/storage`
- Pastikan folder `storage/app/public` dapat ditulis oleh web server
- Aplikasi menyimpan foto/video ke Cloudinary, bukan ke disk lokal

## 8. Migrasi dan Seeding
Untuk deploy produksi, gunakan:
```bash
php artisan migrate --force
php artisan db:seed --force
```

Jika ingin seeding manual tanpa seed default, gunakan hanya migrate.

## 9. Health Check dan Troubleshooting
### 9.1 Pastikan `APP_KEY` sudah valid
Jika `APP_KEY` kosong, jalankan:
```bash
php artisan key:generate
```

### 9.2 Pastikan koneksi database berhasil
- Periksa `DB_CONNECTION`
- Periksa host, port, nama database, user, password

### 9.3 Periksa Cloudinary
- Jika upload media error, cek `CLOUDINARY_*` env values
- Pastikan Cloudinary aktif dan credential benar

### 9.4 Periksa permission
- `storage/`
- `bootstrap/cache`
- `public/storage`

## 10. Catatan Tambahan
- Jangan commit file `.env`.
- Gunakan `.env.example` sebagai template.
- Jika ingin menonaktifkan seeding otomatis di Docker, ubah `CMD` di `Dockerfile`.
- Jika menggunakan Railway, `railway.env.example` membantu membuat variable environment yang dibutuhkan.
