# 🗄️ Dokumentasi Database

## 1. Diagram Entity Relationship (ERD)

```
          ┌──────────────────┐
          │     schools      │
          ├──────────────────┤
          │ id: bigint (PK)  │
          │ name: string     │
          │ address: text    │
          │ pic_name: string │
          │ created_at       │
          │ updated_at       │
          └────────┬─────────┘
                   │ 1:N
          ┌────────▼──────────┐
          │   classes          │
          ├────────────────────┤
          │ id: bigint (PK)    │
          │ school_id: bigint  │◄──────┐
          │ name: string       │       │ school
          │ created_at         │   CASCADE DELETE
          │ updated_at         │       │
          └────────┬───────────┘       │
                   │ 1:N       ┌───────┴──────────┐
          ┌────────▼──────────┐ 1:N
          │   students         │
          ├────────────────────┤
          │ id: bigint (PK)    │
          │ class_id: bigint   │◄──────┐
          │ name: string       │       │ class
          │ created_at         │   CASCADE DELETE
          └────────────────────┘       │
                                       │
          ┌──────────────────────────┐ │
          │     coach_classes        │ │
          ├──────────────────────────┤ │
          │ id: bigint (PK)          │ │
          │ coach_id: bigint (FK)    │─┤──► users.id
          │ class_id: bigint (FK)    │─┘──► classes.id
          │ UNIQUE(coach_id, class_id)
          │ CASCADE DELETE both
          └──────────────────────────┘

          ┌──────────────────────┐
          │      users           │
          ├──────────────────────┤
          │ id: bigint (PK)      │
          │ name: string         │
          │ email: string (UNIQUE)
          │ password: string     │
          │ role: enum           │ admin, coach, school_pic
          │ school_id: bigint    │ nullable, for school_pic
          │ created_at           │
          │ updated_at           │
          └────────┬─────────────┘
                   │ 1:N (admin/coach)
                   │
          ┌────────▼───────────────┐
          │     reports            │
          ├────────────────────────┤
          │ id: bigint (PK)        │
          │ coach_id: bigint (FK)  │──► users (coach)
          │ school_id: bigint (FK) │──► schools
          │ class_id: bigint (FK)  │──► classes
          │ report_date: date      │
          │ lesson_material: text  │
          │ activity_summary: text │
          │ notes: text            │
          │ photo_path: string     │ deprecated
          │ status: enum           │ draft, submitted, approved, rejected
          │ admin_notes: text      │
          │ approved_by: bigint    │──► users (admin)
          │ approved_at: timestamp │
          │ created_at             │
          │ updated_at             │
          └────────┬───────────────┘
                   │ 1:N CASCADE DELETE
        ┌──────────┴────────────┐
        │                       │
   ┌────▼──────────────────┐  ┌────▼──────────────────┐
   │ report_attendances    │  │   report_media        │
   ├──────────────────────┤  ├──────────────────────┤
   │ id: bigint (PK)      │  │ id: bigint (PK)      │
   │ report_id: bigint    │  │ report_id: bigint    │
   │ student_id: bigint   │  │ type: enum           │ photo, video
   │ status: enum         │  │ path: string (URL)   │ Cloudinary
   │ CASCADE DELETE       │  │ original_name: string
   │                      │  │ created_at           │
   │ ◄── students         │  │ updated_at           │
   │ ◄── reports          │  │ CASCADE DELETE       │
   └──────────────────────┘  │ ◄── reports          │
                             └──────────────────────┘
```

---

## 2. Deskripsi Tabel dan Kolom

### Tabel: `schools`
**Tujuan**: Menyimpan data sekolah

| Kolom | Tipe | Null | Deskripsi |
|-------|------|------|-----------|
| id | BIGINT | NO | Primary key, auto increment |
| name | VARCHAR(150) | NO | Nama sekolah |
| address | TEXT | YES | Alamat sekolah |
| pic_name | VARCHAR(100) | YES | Nama PIC (Penanggung Jawab) sekolah |
| created_at | TIMESTAMP | NO | Waktu penciptaan |
| updated_at | TIMESTAMP | NO | Waktu update terakhir |

**Constraints**:
- PRIMARY KEY (id)
- Tidak ada unique constraints khusus

---

### Tabel: `users`
**Tujuan**: Menyimpan data pengguna (Admin, Coach, School PIC)

| Kolom | Tipe | Null | Deskripsi |
|-------|------|------|-----------|
| id | BIGINT | NO | Primary key |
| name | VARCHAR(100) | NO | Nama pengguna |
| email | VARCHAR(150) | NO | Email unik |
| password | VARCHAR(255) | NO | Password terenkripsi (BCrypt) |
| role | ENUM | NO | Enum: admin, coach, school_pic |
| school_id | BIGINT | YES | FK ke schools (hanya untuk school_pic) |
| created_at | TIMESTAMP | NO | Waktu penciptaan |
| updated_at | TIMESTAMP | NO | Waktu update terakhir |

**Constraints**:
- PRIMARY KEY (id)
- UNIQUE KEY (email)
- FOREIGN KEY (school_id) → schools(id) ON DELETE SET NULL

**Notes**:
- `role` menentukan tipe user dan akses
- Hanya `school_pic` yang memiliki `school_id` (bisa NULL untuk admin/coach)

---

### Tabel: `classes`
**Tujuan**: Menyimpan data kelas di sekolah

| Kolom | Tipe | Null | Deskripsi |
|-------|------|------|-----------|
| id | BIGINT | NO | Primary key |
| school_id | BIGINT | NO | FK ke schools |
| name | VARCHAR(100) | NO | Nama kelas (misal: "Kelas 1A", "Grade 5B") |
| created_at | TIMESTAMP | NO | Waktu penciptaan |
| updated_at | TIMESTAMP | NO | Waktu update terakhir |

**Constraints**:
- PRIMARY KEY (id)
- FOREIGN KEY (school_id) → schools(id) ON DELETE CASCADE

**Notes**:
- Setiap kelas harus terkait dengan 1 sekolah
- Jika sekolah dihapus, kelas otomatis dihapus

---

### Tabel: `students`
**Tujuan**: Menyimpan data siswa

| Kolom | Tipe | Null | Deskripsi |
|-------|------|------|-----------|
| id | BIGINT | NO | Primary key |
| class_id | BIGINT | NO | FK ke classes |
| name | VARCHAR(100) | NO | Nama siswa |
| created_at | TIMESTAMP | NO | Waktu penciptaan |

**Constraints**:
- PRIMARY KEY (id)
- FOREIGN KEY (class_id) → classes(id) ON DELETE CASCADE

**Notes**:
- Tabel students TIDAK memiliki `updated_at` (lihat `Student` model)
- Jika kelas dihapus, siswa otomatis dihapus
- Siswa tidak bisa pindah kelas (harus delete + add baru)

---

### Tabel: `coach_classes`
**Tujuan**: Menyimpan assignment coach ke kelas (many-to-many)

| Kolom | Tipe | Null | Deskripsi |
|-------|------|------|-----------|
| id | BIGINT | NO | Primary key |
| coach_id | BIGINT | NO | FK ke users (coach) |
| class_id | BIGINT | NO | FK ke classes |

**Constraints**:
- PRIMARY KEY (id)
- FOREIGN KEY (coach_id) → users(id) ON DELETE CASCADE
- FOREIGN KEY (class_id) → classes(id) ON DELETE CASCADE
- UNIQUE KEY (coach_id, class_id) - satu coach hanya bisa assign 1x per kelas

**Notes**:
- Tabel pivot/junction untuk relasi many-to-many
- Tidak ada timestamps (lihat `CoachClass` model: `public $timestamps = false`)

---

### Tabel: `reports`
**Tujuan**: Menyimpan laporan pembelajaran dari coach

| Kolom | Tipe | Null | Deskripsi |
|-------|------|------|-----------|
| id | BIGINT | NO | Primary key |
| coach_id | BIGINT | NO | FK ke users (coach pembuat) |
| school_id | BIGINT | NO | FK ke schools |
| class_id | BIGINT | NO | FK ke classes |
| report_date | DATE | NO | Tanggal laporan |
| lesson_material | TEXT | NO | Materi pembelajaran |
| activity_summary | TEXT | NO | Ringkasan aktivitas |
| notes | TEXT | YES | Catatan tambahan |
| photo_path | VARCHAR(255) | YES | Path foto (deprecated, gunakan report_media) |
| status | ENUM | NO | Enum: draft, submitted, approved, rejected |
| admin_notes | TEXT | YES | Catatan dari admin saat reject |
| approved_by | BIGINT | YES | FK ke users (admin approver) |
| approved_at | TIMESTAMP | YES | Waktu approval |
| created_at | TIMESTAMP | NO | Waktu penciptaan |
| updated_at | TIMESTAMP | NO | Waktu update terakhir |

**Constraints**:
- PRIMARY KEY (id)
- FOREIGN KEY (coach_id) → users(id)
- FOREIGN KEY (school_id) → schools(id)
- FOREIGN KEY (class_id) → classes(id)
- FOREIGN KEY (approved_by) → users(id) ON DELETE SET NULL

**Status Values**:
- `draft`: Laporan baru, belum dikirim
- `submitted`: Coach sudah submit, menunggu approval admin
- `approved`: Admin menyetujui
- `rejected`: Admin menolak, coach bisa edit ulang

**Notes**:
- `photo_path` deprecated, gunakan tabel `report_media` untuk foto/video
- `admin_notes` hanya diisi saat reject
- `approved_by` dan `approved_at` hanya diisi saat approve

---

### Tabel: `report_attendances`
**Tujuan**: Menyimpan kehadiran siswa dalam setiap laporan

| Kolom | Tipe | Null | Deskripsi |
|-------|------|------|-----------|
| id | BIGINT | NO | Primary key |
| report_id | BIGINT | NO | FK ke reports |
| student_id | BIGINT | NO | FK ke students |
| status | ENUM | NO | Enum: present, absent, sick, permission |

**Constraints**:
- PRIMARY KEY (id)
- FOREIGN KEY (report_id) → reports(id) ON DELETE CASCADE
- FOREIGN KEY (student_id) → students(id) ON DELETE CASCADE

**Status Values**:
- `present`: Hadir
- `absent`: Alpa
- `sick`: Sakit
- `permission`: Izin

**Notes**:
- Tidak ada timestamps
- 1 report = N attendances (satu per siswa di kelas)
- Jika report dihapus, attendances otomatis dihapus

---

### Tabel: `report_media`
**Tujuan**: Menyimpan metadata foto dan video dalam laporan

| Kolom | Tipe | Null | Deskripsi |
|-------|------|------|-----------|
| id | BIGINT | NO | Primary key |
| report_id | BIGINT | NO | FK ke reports |
| type | ENUM | NO | Enum: photo, video |
| path | VARCHAR(255) | NO | URL Cloudinary (HTTPS) |
| original_name | VARCHAR(255) | YES | Nama file asli dari client |
| created_at | TIMESTAMP | NO | Waktu penciptaan |
| updated_at | TIMESTAMP | NO | Waktu update terakhir |

**Constraints**:
- PRIMARY KEY (id)
- FOREIGN KEY (report_id) → reports(id) ON DELETE CASCADE

**Type Values**:
- `photo`: File gambar (jpg, png, etc.)
- `video`: File video (mp4, mov, etc.)

**Notes**:
- `path` adalah URL Cloudinary (tidak path lokal)
- File aktual disimpan di Cloudinary, bukan di server
- Max 10 foto, 3 video per laporan (validasi di controller)

---

## 3. Relationships Overview

### One-to-Many (1:N)
- **School → Classes**: 1 sekolah punya banyak kelas
- **School → Users**: 1 sekolah punya banyak pengguna (PIC)
- **Class → Students**: 1 kelas punya banyak siswa
- **Class → CoachClasses**: 1 kelas punya banyak assignment coach
- **Coach → CoachClasses**: 1 coach punya banyak assignment kelas
- **Coach → Reports**: 1 coach punya banyak laporan
- **Report → ReportAttendances**: 1 laporan punya banyak absensi siswa
- **Report → ReportMedia**: 1 laporan punya banyak media (foto/video)

### Many-to-Many (N:M)
- **Users ↔ Classes** (via `coach_classes`): Coach bisa assign ke banyak kelas

### Cascade Delete Behavior
- Delete `schools` → hapus `classes`, `users` (school_pic only)
- Delete `classes` → hapus `students`, `coach_classes`, `reports`
- Delete `users` (coach) → hapus `coach_classes`, `reports`
- Delete `reports` → hapus `report_attendances`, `report_media`

---

## 4. Indexes

**Current Indexes** (inferred dari migration):
- Primary keys (auto indexed)
- Foreign keys (untuk relasi)

**Recommended Additional Indexes** (untuk performa):
```sql
-- Untuk query laporan by status
CREATE INDEX idx_reports_status ON reports(status);

-- Untuk query laporan by coach
CREATE INDEX idx_reports_coach_id ON reports(coach_id);

-- Untuk query laporan by school
CREATE INDEX idx_reports_school_id ON reports(school_id);

-- Untuk query laporan by report_date
CREATE INDEX idx_reports_report_date ON reports(report_date);

-- Untuk query user by email (login)
CREATE INDEX idx_users_email ON users(email);

-- Untuk query students by class
CREATE INDEX idx_students_class_id ON students(class_id);
```

---

## 5. Data Integrity Rules

### Constraints & Validations

| Rule | Level | Deskripsi |
|------|-------|-----------|
| Email unique | DB | Tidak boleh ada 2 user dengan email sama |
| Coach-Class unique | DB | 1 coach tidak boleh 2x assign ke kelas sama |
| Role enum | DB | Hanya admin, coach, school_pic |
| Status enum | DB | draft, submitted, approved, rejected |
| Attendance status enum | DB | present, absent, sick, permission |
| Media type enum | DB | photo, video |
| Foreign keys | DB | Relasi harus valid |
| Cascade delete | DB | Jika parent hapus, child juga hapus |

### Application-Level Validations

| Validasi | Lokasi | Deskripsi |
|----------|--------|-----------|
| Report status check | Controller | Laporan hanya bisa diedit jika draft/rejected |
| Coach authorization | Controller | Coach hanya bisa lihat/edit laporan miliknya |
| Admin authorization | Controller | Admin check role sebelum akses |
| School PIC filter | Controller | PIC hanya lihat laporan sekolahnya (approved) |
| Unique student name | Controller | Tidak boleh ada siswa duplikat di 1 kelas |
| File type validation | Controller | Foto harus image, video harus video |

---

## 6. Data Flow dalam Database

### Saat Coach Submit Laporan

```
1. INSERT reports table
   coach_id: [coach yang login]
   school_id: [dari class.school_id]
   class_id: [dari form]
   report_date: [dari form]
   lesson_material: [dari form]
   activity_summary: [dari form]
   status: 'submitted'
   created_at: now()

2. INSERT report_media (per photo)
   report_id: [report yang baru dibuat]
   type: 'photo'
   path: [URL dari Cloudinary]
   original_name: [nama file asli]

3. INSERT report_media (per video)
   report_id: [report yang baru dibuat]
   type: 'video'
   path: [URL dari Cloudinary]
   original_name: [nama file asli]

4. INSERT report_attendances (per student)
   report_id: [report yang baru dibuat]
   student_id: [dari form]
   status: [present/absent/sick/permission]
```

### Saat Admin Approve

```
UPDATE reports
SET
  status: 'approved',
  approved_by: [admin yang login],
  approved_at: now()
WHERE id = [report_id]
```

### Saat Admin Reject

```
UPDATE reports
SET
  status: 'rejected',
  admin_notes: [alasan reject]
WHERE id = [report_id]
```

---

## 7. Query Patterns Umum

### Get all reports pending approval
```sql
SELECT r.*, u.name as coach_name, s.name as school_name, c.name as class_name
FROM reports r
JOIN users u ON r.coach_id = u.id
JOIN schools s ON r.school_id = s.id
JOIN classes c ON r.class_id = c.id
WHERE r.status = 'submitted'
ORDER BY r.created_at DESC
LIMIT 20;
```

### Get approved reports for specific school
```sql
SELECT r.*, u.name as coach_name, c.name as class_name
FROM reports r
JOIN users u ON r.coach_id = u.id
JOIN classes c ON r.class_id = c.id
WHERE r.school_id = [school_id]
  AND r.status = 'approved'
ORDER BY r.report_date DESC;
```

### Get report detail with all relations
```sql
SELECT r.*, 
       u.name as coach_name,
       a.name as admin_name,
       s.name as school_name,
       c.name as class_name
FROM reports r
LEFT JOIN users u ON r.coach_id = u.id
LEFT JOIN users a ON r.approved_by = a.id
LEFT JOIN schools s ON r.school_id = s.id
LEFT JOIN classes c ON r.class_id = c.id
WHERE r.id = [report_id];

-- Then query relations separately:
SELECT * FROM report_attendances WHERE report_id = [report_id];
SELECT * FROM report_media WHERE report_id = [report_id];
```

### Get coach's classes
```sql
SELECT c.*, s.name as school_name
FROM classes c
JOIN coach_classes cc ON c.id = cc.class_id
JOIN schools s ON c.school_id = s.id
WHERE cc.coach_id = [coach_id];
```

