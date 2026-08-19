# 🔐 Autentikasi & Otorisasi

**Terakhir diperbarui:** Sesuai dengan status root project LRS terbaru.

---

## 1. Sistem Autentikasi

### 1.1 Tipe Autentikasi
- **Tipe**: Session-based authentication (stateful)
- **Framework**: Laravel built-in authentication
- **Driver**: Database session driver (saat ini fallback ke `file` pada konfigurasi lokal)
- **Password Hash**: Bcrypt

---

## 2. Login Flow & Redirect

### Langkah-langkah Proses Login

1. User mengakses `/login`. Jika sudah login, langsung di-redirect ke halaman utama sesuai role.
2. Form submit (POST `/login`) melakukan validasi email & password.
3. `Auth::attempt($credentials)` mengecek kecocokan dengan hash bcrypt di tabel `users`.
4. Jika berhasil, session di-regenerate untuk mencegah *session fixation*.
5. Redirect ditentukan secara eksplisit lewat `LoginController::redirectByRole()`.

### Redirect Berdasarkan Role

Berbeda dengan sistem lama yang hanya punya 3 role, kini sistem memiliki 7 role yang diatur ketat:

```php
return match ($role) {
    User::ROLE_SUPERADMIN     => redirect()->route('admin.dashboard'),
    User::ROLE_RELATION       => redirect()->route('admin.schools.index'),
    User::ROLE_SPV_COACH      => redirect()->route('admin.coaches.index'),
    User::ROLE_COACH          => redirect()->route('coach.reports.index'),
    User::ROLE_SCHOOL_PIC     => redirect()->route('pic.dashboard'),
    User::ROLE_TEACHER_SCHOOL => redirect()->route('attendance.index'),
    User::ROLE_FINANCE        => redirect()->route('attendance.index'),
    default                   => abort(403, 'Role akun belum memiliki halaman awal. Hubungi SuperAdmin.'),
};
```

---

## 3. Otorisasi Berbasis Capability (RBAC Baru)

Sistem LRS modern tidak lagi mengecek nama role mentah secara langsung di dalam controller (seperti `if ($role === 'admin')`). Sebaliknya, sistem menggunakan pendekatan **Capability-Based Access Control** yang terpusat di `AuthorizationService`.

### 3.1 Tujuh Role dalam Sistem

| Role | Kode Key | Tanggung Jawab Akses | Scope Data |
|------|-----------|--------|---|
| **SuperAdmin** | `superadmin` | Akses mutlak (wildcard), Manajemen User | Global |
| **Relation (Admin)** | `relation` | Manajemen Master Data, Review Laporan | Global |
| **SPV Coach** | `spv_coach` | Manajemen Coach, Assignment Kelas | Global |
| **Coach** | `coach` | Input laporan & absen di kelas tugasnya | Kelas *assigned* |
| **School PIC** | `school_pic` | Lihat laporan *approved* & dashboard PIC | *School-scoped* |
| **Teacher School** | `teacher_school` | Lihat laporan & absen sekolahnya | *School-scoped* |
| **Finance** | `finance` | Akses data absensi untuk tagihan/keuangan | *School-scoped* |

*(Role `admin` lama telah ditiadakan dan dimigrasikan menjadi `relation`).*

### 3.2 AuthorizationService

File `app/Services/AuthorizationService.php` memegang peta *permissions* untuk setiap role. Hal ini mencegah logika `if` yang tersebar di controller.

Sistem bekerja berdasarkan prinsip **Allow-List ketat**: jika permission tidak secara eksplisit diberikan kepada role tersebut di dalam array `ROLE_PERMISSIONS`, maka akses ditolak secara default. (Pengecualian untuk `SuperAdmin` yang selalu mengembalikan `true`).

---

## 4. Middleware Otorisasi

Terdapat 3 tipe middleware yang digunakan untuk melindungi *route*:

1. **`permission:<cap>`** (`PermissionMiddleware`)
   Mewajibkan user memiliki satu *capability* spesifik.
   *Contoh:* `Route::get('/attendance', ...)->middleware('permission:attendance.view');`

2. **`permission_any:<a>,<b>`** (`PermissionAnyMiddleware`)
   Mengizinkan akses jika user memiliki *minimal satu* dari *capabilities* yang didaftarkan. Digunakan untuk rute *sharing* lintas divisi (seperti export CSV).

3. **`role:<x>,<y>`** (`RoleMiddleware`)
   Berfungsi sebagai *coarse gate* (gerbang kasar) untuk memblokir akses ke area portal spesifik, sering kali dikombinasikan dengan middleware *permission*.

---

## 5. Scope Kepemilikan Data (Data Isolation)

Selain *capability* (apakah user BISA mengakses halaman X?), sistem juga memiliki perlindungan **Scope Kepemilikan Data** (data apa saja yang BISA DILIHAT oleh user tersebut di halaman X?).

Ini diatur oleh metode `accessibleSchoolIds(User $user)` di `AuthorizationService`:

- **Operasional Global** (SuperAdmin, Relation, SPV Coach) → Mengembalikan `null` (Tidak difilter).
- **Akun Terbatas / School-scoped** (School PIC, Teacher School, Finance) → Mengembalikan array ID sekolah dari tabel pivot `school_user` (dan legacy `school_id`). Jika `[]`, query akan terfilter kosong sehingga tidak membocorkan data sekolah lain.
- **Coach** → Data yang dilihat dibatasi lewat query ke relasi penugasan kelas (`coach_classes`).

---

## 6. Perlindungan Tambahan (Security Best Practices)

- **CSRF Token:** Setiap request POST/PUT/DELETE dari UI dilindungi secara otomatis oleh Laravel.
- **Isolasi Logika Query:** Pembuatan *query* untuk mengekstrak data sensitif (seperti absensi) dibungkus secara terpusat di `AttendanceScopeService`. Controller dan fitur *Export CSV* sama-sama memanggil service ini agar dipastikan tidak ada filter data yang lolos.
- **Validasi Form Request yang Kuat:** Laravel Validation digunakan di seluruh endpoint penyimpanan/pembaruan untuk mencegah injeksi atau manipulasi parameter tak wajar (seperti manipulasi `array keys` pada absen).
- **Password Bcrypt:** Dengan kompleksitas Work Factor 10, perlindungan timing-safe standar framework.
