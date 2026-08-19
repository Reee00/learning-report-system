# 🗄️ Dokumentasi Database

**Terakhir diperbarui:** 17 Agustus 2026
**Sumber:** `PRAGMA table_info` / `PRAGMA foreign_key_list` / `PRAGMA index_list` pada
`database/database.sqlite` + 13 file migration

---

## 1. Ringkasan

| Hal | Nilai |
|---|---|
| DBMS lokal | SQLite |
| Jumlah migration | **13** (`php artisan migrate:status` → 13 Ran, 0 Pending) |
| Jumlah tabel aplikasi | **13** (termasuk `migrations` & `sessions`) |
| Batch | batch 1 = 9 migration, batch 2 = 4 migration |
| SoftDeletes | tidak dipakai di tabel mana pun |
| Database test | SQLite `:memory:` + `RefreshDatabase` — test tidak pernah menyentuh file dev |

Daftar migration:

```text
batch 1 (Februari–Maret 2026)
  0001_01_01_000000_create_users_table
  0001_01_01_000001_create_cache_table
  0001_01_01_000002_create_jobs_table
  ..._create_schools_table
  ..._create_classes_table
  ..._create_students_table
  ..._create_coach_classes_table
  ..._create_reports_table
  ..._create_report_attendances_table
  ..._create_report_media_table

batch 2 (Agustus 2026)
  2026_08_14_000000_migrate_admin_role_to_relation_and_expand_roles
  2026_08_17_000000_create_programs_table
  2026_08_17_000001_create_program_classes_table
  2026_08_17_000002_create_school_user_table
```

## 2. Daftar Tabel

| Tabel | Fungsi | Timestamps |
|---|---|---|
| `users` | Akun semua role (termasuk Coach) | ✅ |
| `schools` | Master sekolah | ✅ |
| `classes` | Program Kelas milik sekolah | ✅ |
| `students` | Siswa per kelas | ⚠️ hanya `created_at` |
| `coach_classes` | Assignment Coach ↔ Kelas | ❌ |
| `reports` | Laporan pembelajaran | ✅ |
| `report_attendances` | Absensi per siswa per laporan | ❌ |
| `report_media` | Foto/video laporan | ✅ |
| `programs` | Master Program (reusable) | ✅ |
| `program_classes` | Pivot Program ↔ Kelas | ✅ |
| `school_user` | Pivot plotting Sekolah ↔ User | ✅ |
| `sessions` | Session store Laravel | ❌ (pakai `last_activity`) |
| `migrations` | Internal Laravel | ❌ |

Catatan: driver session aktif adalah **`file`**, jadi tabel `sessions` ada tetapi tidak terpakai pada
konfigurasi saat ini. Mengubah `SESSION_DRIVER=database` akan langsung memakainya tanpa migration baru.

## 3. Skema Per Tabel

### 3.1 `users`

| Kolom | Tipe | Null | Default | Catatan |
|---|---|---|---|---|
| `id` | INTEGER | ❌ | AI | PK |
| `name` | varchar | ✅ | — | |
| `email` | varchar | ✅ | — | **UNIQUE** |
| `password` | varchar | ✅ | — | bcrypt (`$2y$`) |
| `role` | varchar | ❌ | — | salah satu dari 6 role key |
| `school_id` | INTEGER | ✅ | NULL | legacy single-school; FK → `schools` **SET NULL** |
| `created_at` / `updated_at` | datetime | ✅ | — | |

Nilai `role` yang sah — konstanta di `App\Models\User`:

```php
ROLE_SUPERADMIN = 'superadmin'
ROLE_RELATION   = 'relation'
ROLE_SPV_COACH  = 'spv_coach'
ROLE_COACH      = 'coach'
ROLE_SCHOOL_PIC = 'school_pic'
ROLE_TEACHER_SCHOOL = 'teacher_school'
ROLE_FINANCE    = 'finance'
```

Kolom ini **bukan** enum di level database — validasinya di aplikasi lewat
`Rule::in(User::roleKeys())`. Konsekuensi: penulisan langsung via SQL bisa memasukkan role tak dikenal.
Aman secara akses (allow-list `allows()` mengembalikan `false` untuk role tak dikenal), tetapi user
tersebut akan gagal login dengan 403 dari `redirectByRole()`.

Role `admin` versi lama sudah tidak ada — dimigrasikan oleh
`2026_08_14_000000_migrate_admin_role_to_relation_and_expand_roles`.

**Dua sumber scope sekolah.** `users.school_id` (legacy) dan pivot `school_user` (multi-sekolah)
digabung oleh:

```php
public function assignedSchoolIds(): array
{
    $ids = $this->schools()->pluck('schools.id')->all();
    if ($this->school_id) { $ids[] = $this->school_id; }
    return array_values(array_unique(array_map('intval', $ids)));
}
```

Penggabungan ini disengaja: akun lama yang hanya punya `school_id` dan akun baru yang hanya punya baris
pivot keduanya tetap ter-scope. Tidak ada bentuk record yang membuat akun jadi tanpa scope.

### 3.2 `schools`

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `id` | INTEGER | ❌ | PK |
| `name` | varchar | ❌ | wajib (`required|max:150`) |
| `address` | text/varchar | ✅ | |
| `pic_name` | varchar | ✅ | nama PIC sebagai teks, bukan FK ke `users` |
| `created_at` / `updated_at` | datetime | ✅ | |

`pic_name` adalah label deskriptif. Relasi PIC yang **sesungguhnya** menentukan akses adalah
`school_user` / `users.school_id`. Mengubah `pic_name` tidak mengubah hak akses siapa pun.

### 3.3 `classes` — "Program Kelas"

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `id` | INTEGER | ❌ | PK |
| `school_id` | INTEGER | ❌ | FK → `schools` **CASCADE** |
| `name` | varchar | ❌ | `required|max:100` |
| `created_at` / `updated_at` | datetime | ✅ | |

Model-nya bernama `SchoolClass` (bukan `Class`, karena `class` adalah keyword PHP). Nama tabel
di-override eksplisit:

```php
protected $table = 'classes';
```

### 3.4 `students`

| Kolom | Tipe | Null | Default | Catatan |
|---|---|---|---|---|
| `id` | INTEGER | ❌ | AI | PK |
| `class_id` | INTEGER | ❌ | — | FK → `classes` **CASCADE** |
| `name` | varchar | ❌ | — | `required|max:100` |
| `created_at` | datetime | ✅ | `CURRENT_TIMESTAMP` | |

**Tidak ada `updated_at`.** Karena itu model mematikan timestamps otomatis:

```php
public $timestamps = false;
```

Kalau `$timestamps` dibiarkan `true`, setiap `save()` akan mencoba menulis `updated_at` dan gagal.

Tidak ada unique constraint pada `(class_id, name)`. Pencegahan duplikat dilakukan di aplikasi:

```php
// StudentController@store
if ($class->students()->where('name', $validated['name'])->exists()) {
    return back()->withErrors(['name' => 'Siswa dengan nama tersebut sudah ada di kelas ini.']);
}
```

### 3.5 `coach_classes`

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `id` | INTEGER | ❌ | PK |
| `coach_id` | INTEGER | ❌ | FK → `users` **CASCADE** |
| `class_id` | INTEGER | ❌ | FK → `classes` **CASCADE** |

**UNIQUE (`coach_id`, `class_id`)** — assignment ganda ditolak di level database. Aplikasi memakai
`CoachClass::firstOrCreate()` supaya penolakan itu berubah menjadi pesan, bukan exception:

```php
// Admin\CoachController@assign
$existing = CoachClass::where('coach_id', $coach->id)->where('class_id', $class->id)->first();
if ($existing) {
    return back()->with('error', 'Coach sudah di-assign ke kelas ini.');
}
```

Tabel ini adalah **satu-satunya sumber kebenaran** untuk pertanyaan "kelas mana yang boleh dilaporkan
Coach ini". `Coach\ReportController::assignedClassOrFail()` membacanya dan tidak mempercayai `class_id`
dari form.

### 3.6 `reports`

| Kolom | Tipe | Null | Default | Catatan |
|---|---|---|---|---|
| `id` | INTEGER | ❌ | AI | PK |
| `coach_id` | INTEGER | ❌ | — | FK → `users` **NO ACTION** |
| `school_id` | INTEGER | ❌ | — | FK → `schools` **NO ACTION** |
| `class_id` | INTEGER | ❌ | — | FK → `classes` **NO ACTION** |
| `report_date` | date | ❌ | — | |
| `lesson_material` | text | ❌ | — | `max:1000` |
| `activity_summary` | text | ❌ | — | `max:2000` |
| `notes` | text | ✅ | NULL | **inilah Accident Notes** |
| `photo_path` | varchar | ✅ | NULL | legacy single-photo; media baru di `report_media` |
| `status` | varchar | ✅ | `'draft'` | |
| `admin_notes` | text | ✅ | NULL | alasan reject |
| `approved_by` | INTEGER | ✅ | NULL | FK → `users` **SET NULL** |
| `approved_at` | datetime | ✅ | NULL | |
| `created_at` / `updated_at` | datetime | ✅ | — | |

**Nilai `status`:** `draft` · `submitted` · `approved` · `rejected`.

Default kolom adalah `draft`, tetapi laporan yang dikirim Coach lewat UI dibuat dengan
`status = 'submitted'` secara eksplisit. **Tidak ada status `pending`** — dokumentasi lama yang
menyebutnya salah.

`notes` merangkap sebagai Accident Notes (Phase 12). Tidak ada tabel terpisah: kalau `notes` tidak
kosong, detail laporan menampilkannya sebagai blok merah urgent lewat partial
`resources/views/partials/accident-notes.blade.php`. Isi multi-baris dipertahankan dengan
`{!! nl2br(e($report->notes)) !!}`.

### 3.7 `report_attendances`

| Kolom | Tipe | Null | Default | Catatan |
|---|---|---|---|---|
| `id` | INTEGER | ❌ | AI | PK |
| `report_id` | INTEGER | ❌ | — | FK → `reports` **CASCADE** |
| `student_id` | INTEGER | ❌ | — | FK → `students` **CASCADE** |
| `status` | varchar | ✅ | `'present'` | `present` · `absent` · `sick` · `permission` |

Tanpa timestamps → `public $timestamps = false;`.

Tidak ada unique `(report_id, student_id)`. Aplikasi menulisnya sekali per laporan di dalam transaksi,
dan pada `update()` baris lama dihapus dulu sebelum ditulis ulang.

**Key array `attendance` adalah input juga.** Form mengirim `attendance[student_id] => status`. Validasi
Laravel memeriksa *value*-nya (`in:present,absent,sick,permission`) tetapi tidak memeriksa *key*-nya,
sehingga dulu siswa dari kelas lain bisa ditempelkan ke laporan (BUG-004). Sekarang diperiksa eksplisit:

```php
private function assertAttendanceBelongsToClass(array $attendance, int $classId): void
{
    $studentIds = array_map('intval', array_keys($attendance));
    $validCount = Student::where('class_id', $classId)->whereIn('id', $studentIds)->count();
    abort_unless($validCount === count($studentIds), 422, 'Data absensi tidak valid untuk kelas ini.');
}
```

### 3.8 `report_media`

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `id` | INTEGER | ❌ | PK |
| `report_id` | INTEGER | ❌ | FK → `reports` **CASCADE** |
| `type` | varchar | ❌ | `photo` \| `video` |
| `path` | varchar | ❌ | `secure_url` dari Cloudinary |
| `original_name` | varchar | ✅ | nama file asli dari user |
| `created_at` / `updated_at` | datetime | ✅ | |

⚠️ **Tidak ada kolom `cloudinary_public_id`** (MEDIA-001 / ISSUE-015). Karena `delete()` Cloudinary
membutuhkan public_id, penghapusan baris `report_media` **tidak** menghapus aset di Cloudinary — aset
menjadi orphan. Perbaikannya memerlukan migration penambahan kolom + backfill, dan itu di luar scope
dokumentasi ini; didokumentasikan, tidak diubah.

### 3.9 `programs`

| Kolom | Tipe | Null | Default | Catatan |
|---|---|---|---|---|
| `id` | INTEGER | ❌ | AI | PK |
| `name` | varchar | ❌ | — | `required|max:150` |
| `code` | varchar | ✅ | NULL | **UNIQUE**, `nullable|max:50` |
| `description` | text | ✅ | NULL | |
| `status` | varchar | ✅ | `'active'` | `active` \| `inactive` |
| `created_at` / `updated_at` | datetime | ✅ | — | |

`code` unique tetapi nullable — beberapa program tanpa kode diperbolehkan (SQLite memperlakukan setiap
NULL sebagai unik).

### 3.10 `program_classes`

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `id` | INTEGER | ❌ | PK |
| `program_id` | INTEGER | ❌ | FK → `programs` **CASCADE** |
| `class_id` | INTEGER | ❌ | FK → `classes` **CASCADE** |
| `created_at` / `updated_at` | datetime | ✅ | |

**UNIQUE (`program_id`, `class_id`)**. Ditulis lewat `sync()` di dalam transaksi:

```php
DB::transaction(function () use ($validated) {
    $program = Program::create([...]);
    $program->classes()->sync($validated['class_ids']);
});
```

### 3.11 `school_user`

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `id` | INTEGER | ❌ | PK |
| `school_id` | INTEGER | ❌ | FK → `schools` **CASCADE** |
| `user_id` | INTEGER | ❌ | FK → `users` **CASCADE** |
| `created_at` / `updated_at` | datetime | ✅ | |

**UNIQUE (`school_id`, `user_id`)**. Tabel inti School Plotting (Phase 9). Diisi lewat
`Admin\UserController` dengan `$user->schools()->sync($schoolIds)`, dan wajib berisi minimal satu baris
untuk role ter-scope:

```php
if (in_array($validated['role'], User::schoolScopedRoles(), true) && empty($schoolIds)) {
    return back()->withErrors(['school_ids' => 'Minimal satu sekolah wajib dipilih untuk role ini.']);
}
```

`User::schoolScopedRoles()` = `[ROLE_SCHOOL_PIC, ROLE_TEACHER_SCHOOL, ROLE_FINANCE]`.

### 3.12 `sessions`

`id` (varchar PK) · `user_id` · `ip_address` · `user_agent` · `payload` (NOT NULL) · `last_activity`
(NOT NULL). Tidak terpakai selama `SESSION_DRIVER=file`.

## 4. Foreign Key Lengkap

Hasil `PRAGMA foreign_key_list` untuk seluruh tabel — **bukan** asumsi.

| Tabel anak | Kolom | Induk | onDelete |
|---|---|---|---|
| `classes` | `school_id` | `schools` | CASCADE |
| `students` | `class_id` | `classes` | CASCADE |
| `coach_classes` | `coach_id` | `users` | CASCADE |
| `coach_classes` | `class_id` | `classes` | CASCADE |
| `report_attendances` | `report_id` | `reports` | CASCADE |
| `report_attendances` | `student_id` | `students` | CASCADE |
| `report_media` | `report_id` | `reports` | CASCADE |
| `program_classes` | `program_id` | `programs` | CASCADE |
| `program_classes` | `class_id` | `classes` | CASCADE |
| `school_user` | `school_id` | `schools` | CASCADE |
| `school_user` | `user_id` | `users` | CASCADE |
| `users` | `school_id` | `schools` | SET NULL |
| `reports` | `approved_by` | `users` | SET NULL |
| `reports` | `school_id` | `schools` | **NO ACTION** |
| `reports` | `class_id` | `classes` | **NO ACTION** |
| `reports` | `coach_id` | `users` | **NO ACTION** |

### Tiga FK terakhir adalah keputusan desain, bukan kelalaian

Dokumentasi versi sebelumnya menyatakan `reports` ikut CASCADE saat sekolah/kelas dihapus. **Itu salah.**
`NO ACTION` pada SQLite berperilaku seperti RESTRICT: database menolak penghapusan induk selama masih
ada laporan yang menunjuk ke sana.

Alasannya: laporan adalah catatan historis. Menghapus sekolah tidak boleh menghapus jejak pembelajaran
yang sudah terjadi.

Tanpa penjagaan di aplikasi, penolakan itu muncul sebagai
`SQLSTATE[23000] Integrity constraint violation: FOREIGN KEY constraint failed` → HTTP 500. Itu BUG-007,
tercatat 6 kali di log. Sekarang setiap `destroy()` memeriksa lebih dulu:

```php
// Admin\SchoolController@destroy
if ($school->reports()->exists()) {
    return back()->with('error', 'Sekolah tidak bisa dihapus karena masih memiliki laporan. '
        .'Hapus atau pindahkan laporan terkait terlebih dahulu.');
}

// Admin\ClassController@destroy   — pola sama
// Admin\UserController@destroy    — pola sama untuk Coach yang punya laporan
```

Diuji oleh 6 test berpasangan di `MasterDataIntegrityTest`: tiga "ditolak dengan pesan yang bisa dibaca"
dan tiga "tetap bisa dihapus kalau tidak punya dependent".

## 5. Index

| Tabel | Index | Jenis |
|---|---|---|
| `users` | `email` | UNIQUE |
| `coach_classes` | (`coach_id`, `class_id`) | UNIQUE |
| `program_classes` | (`program_id`, `class_id`) | UNIQUE |
| `school_user` | (`school_id`, `user_id`) | UNIQUE |
| `programs` | `code` | UNIQUE |
| semua tabel | `id` | PK |

Kolom FK **tidak** diberi index tambahan secara eksplisit. Pada volume saat ini tidak ada masalah, dan
tidak ada optimasi spekulatif yang dilakukan (audit performa: seluruh list screen sudah eager-loaded dan
paginated). Kalau `reports` tumbuh besar, kandidat pertama adalah index komposit
(`school_id`, `report_date`) dan (`coach_id`, `report_date`) — belum diperlukan.

## 6. Data Seeder

`database/seeders/DatabaseSeeder.php` bersifat **idempoten** (`updateOrCreate` / `firstOrCreate`), jadi
menjalankannya dua kali tidak menduplikasi baris (BUG-008, sudah diperbaiki).

Akun demo — semua password `password`, semua adalah kredensial publik untuk pengembangan:

| Email | Nama | Role | Scope sekolah |
|---|---|---|---|
| `superadmin@lrs.com` | SuperAdmin Utama | `superadmin` | `null` |
| `admin@lrs.com` | Relation Utama | `relation` | `null` |
| `spv@lrs.com` | Sari Supervisor | `spv_coach` | `null` |
| `coach@lrs.com` | Rina Coachella | `coach` | `null` |
| `pic@lrs.com` | Budi Santoso | `school_pic` | `school_id` + pivot → SD Harapan Bangsa |
| `finance@lrs.com` | Fajar Finance | `finance` | `school_id` + pivot → SD Harapan Bangsa |

Master data yang dibuat: sekolah **SD Harapan Bangsa** (Jl. Merdeka No. 10, Jakarta; `pic_name` Budi
Santoso), kelas **Grade 5A**, lima siswa (Andi Pratama, Bela Sari, Citra Dewi, Dito Arifin, Eka Putri),
dan satu `CoachClass` yang menghubungkan `coach@lrs.com` ke Grade 5A.

Perhatikan: `admin@lrs.com` sekarang ber-role **`relation`**, bukan `admin`. Emailnya dipertahankan supaya
kredensial dev lama tetap berfungsi.

## 7. Integrity Check

16 pemeriksaan dijalankan pada audit 17 Agustus 2026, semuanya bersih (0 dari 16 gagal). Yang perlu
diketahui saat memelihara data:

| # | Aturan |
|---|---|
| 01–06 | Tidak ada orphan pada `classes`, `students`, `coach_classes`, `reports`, `report_attendances`, `report_media` |
| 07 | Tidak ada `users.role` di luar 7 role key |
| 08 | Tidak ada role ter-scope tanpa plotting (`school_user` maupun `school_id`) |
| 09 | Tidak ada duplikat pada tiga pivot unique |
| 10 | Tidak ada `reports.status` di luar 4 nilai yang sah |
| 11 | Tidak ada `report_attendances.status` di luar 4 nilai yang sah |
| 12 | `approved_by`/`approved_at` konsisten dengan `status = approved` |
| 13 | Tidak ada password plaintext (semua `$2y$`) |
| 14 | Tidak ada email duplikat |
| 15 | `reports.school_id` konsisten dengan `classes.school_id` |
| **16** | **Tidak ada laporan `submitted`/`approved` dengan nol baris absensi** |

Check 16 ditambahkan karena BUG-012: upload Cloudinary gagal → `Undefined array key "secure_url"` →
request mati **setelah** baris `reports` commit tetapi **sebelum** loop absensi jalan. Hasilnya laporan
`submitted` dengan nol absensi — tidak muncul di list mana pun dan tidak terdeteksi 15 check struktural
pertama. Dua record rusak seperti ini (DATA-001), keduanya sudah diperbaiki pemilik lewat UI, bukan lewat
SQL. Penulisan laporan sekarang dibungkus `DB::transaction()` sehingga bentuk data itu tidak bisa terjadi
lagi. Detail: [stabilization/data-integrity-report.md](stabilization/data-integrity-report.md).

## 8. Query Pattern yang Dipakai

```php
// Scope sekolah — null berarti tanpa filter, array berarti dibatasi
$ids = $authorization->accessibleSchoolIds($user);
if ($ids !== null) {
    $query->whereIn('school_id', $ids);
}
```

`$ids !== null`, **bukan** `if ($ids)`. Array kosong `[]` bersifat falsy di PHP, jadi `if ($ids)` akan
melewati filter dan menampilkan **semua** sekolah untuk akun ter-scope tanpa plotting — kebocoran yang
persis kebalikan dari niatnya.

```php
// Absensi — scope lebih dulu, filter request sesudahnya
ReportAttendance::query()
    ->with(['student', 'report.school', 'report.schoolClass', 'report.coach'])
    ->whereHas('report', fn ($q) => $this->scopeReports($q, $user))     // 1. scope
    ->when($filters['school_id'] ?? null, fn ($q, $v) =>                // 2. filter
        $q->whereHas('report', fn ($r) => $r->where('school_id', $v)));
```

```php
// Export — chunk, jangan get() semuanya
$query->chunkById(500, function ($rows) use ($handle) { /* fputcsv */ });
```

## 9. Backup & Pemeliharaan

```bash
# Backup manual (SQLite = satu file)
cp database/database.sqlite database/database.sqlite.bak-$(date +%Y%m%d)

# Status migration
php artisan migrate:status

# Migrasi baru
php artisan migrate
```

⚠️ **Jangan** `migrate:fresh` atau `migrate:refresh` pada database yang berisi data nyata — keduanya
menghapus seluruh tabel. Untuk pengembangan gunakan database terpisah.

Dua file backup masih tertinggal di repo (`database/database.sqlite.bak-20260817`,
`database/database.sqlite.bak-preseed-20260817`) bersama tiga script debug root (`_dbcheck.php`,
`_logincheck.php`, `_audit_db.php`). Tercatat sebagai ISSUE-013 / P3-4 dan disarankan dihapus — **tidak**
dihapus di sini karena aturan "dokumentasikan dulu sebelum menghapus".

## 10. Yang Tidak Ada di Database

| Hal | Status |
|---|---|
| Tabel `roles` / `permissions` | Tidak ada — peta capability statis di `AuthorizationService` |
| Tabel `notifications` | Tidak ada — Phase 13 |
| Tabel `jobs` / worker aktif | Migration `create_jobs_table` ada, tetapi `QUEUE_CONNECTION=sync` |
| Kolom `cloudinary_public_id` | Tidak ada — MEDIA-001 |
| SoftDeletes | Tidak ada di tabel mana pun |
| Tabel accident notes | Tidak ada — memakai `reports.notes` |
