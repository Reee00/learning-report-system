# 🏗️ Arsitektur Sistem

## 1. Arsitektur Keseluruhan

```
┌─────────────────────────────────────────────────────────────┐
│                    BROWSER / CLIENT                          │
├─────────────────────────────────────────────────────────────┤
│                    LARAVEL APPLICATION                       │
│  ┌───────────────────────────────────────────────────────┐  │
│  │         ROUTING (routes/web.php)                      │  │
│  │    ↓                                                   │  │
│  │  MIDDLEWARE (RoleMiddleware, Auth)                    │  │
│  │    ↓                                                   │  │
│  │  CONTROLLERS (Request Handling)                       │  │
│  │    ↓                                                   │  │
│  │  MODELS (Business Logic + Database)                   │  │
│  │    ↓                                                   │  │
│  │  VIEWS (Blade Templates Response)                     │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
         ↓                                           ↓
    ┌─────────────┐                         ┌──────────────┐
    │ DATABASE    │                         │ CLOUDINARY   │
    │ MySQL/PG    │                         │ (Media)      │
    └─────────────┘                         └──────────────┘
```

---

## 2. MVC Pattern Implementation

### Model Layer (`app/Models/`)
```
User → School
         ↓
    SchoolClass → Student
         ↓
    CoachClass
         ↓
    Report → ReportAttendance
             → ReportMedia
```

**Tanggung Jawab:**
- Definisi struktur data
- Business logic query
- Relasi antar entitas

### Controller Layer (`app/Http/Controllers/`)

| Controller | Fungsi |
|-----------|--------|
| `Auth/LoginController` | Handle login/logout |
| `Admin/DashboardController` | Dashboard admin |
| `Admin/UserController` | Manajemen user |
| `Admin/SchoolController` | Manajemen sekolah |
| `Admin/ClassController` | Manajemen kelas |
| `Admin/CoachController` | Manajemen coach + assignment |
| `Admin/ReportController` | Review + approve/reject laporan |
| `Coach/ReportController` | CRUD laporan pembelajaran |
| `SchoolPic/DashboardController` | Dashboard PIC |
| `StudentController` | CRUD siswa |

### View Layer (`resources/views/`)

```
views/
├── auth/
│   └── login.blade.php
├── admin/
│   ├── dashboard.blade.php
│   ├── users/
│   ├── master/ (schools, classes, coaches)
│   └── reports/
├── coach/
│   └── reports/ (index, create, edit)
├── school_pic/
│   └── dashboard.blade.php
├── students/
│   └── index.blade.php
└── layouts/
    └── app.blade.php (shared layout)
```

---

## 3. Request Lifecycle

### Contoh: Coach Membuat Laporan

```
1. GET /coach/reports/create
   ↓
   Route Handler → CoachReportController@create
   ↓
   Load: Classes yang di-assign ke coach
   ↓
   Render: coach/reports/create.blade.php
   ↓
   Browser: Form Laporan

2. POST /coach/reports
   ↓
   Route Handler → CoachReportController@store
   ↓
   Validate Request
   ↓
   Create Report + ReportAttendance + ReportMedia (via Cloudinary)
   ↓
   Redirect: /coach/reports (dengan success message)
   ↓
   Status: submitted (menunggu approval admin)
```

---

## 4. Authentication & Authorization Flow

```
┌─────────────────────────────────────────────────────────┐
│                    LOGIN PAGE                            │
└─────────────────────────────────────────────────────────┘
         ↓ POST email + password
┌─────────────────────────────────────────────────────────┐
│              Auth::attempt(credentials)                 │
│          Query: users WHERE email = ...                │
│          Check: password_hash verification             │
└─────────────────────────────────────────────────────────┘
         ↓ SUCCESS
   Session Created
   User ID stored in session
         ↓
┌─────────────────────────────────────────────────────────┐
│         Redirect berdasarkan Role                        │
│    - admin → /admin/dashboard                            │
│    - coach → /coach/reports                              │
│    - school_pic → /pic/dashboard                         │
└─────────────────────────────────────────────────────────┘
```

### Authorization Middleware

Setiap rute dilindungi oleh:
1. **`auth`** - Pastikan user sudah login
2. **`role:admin|coach|school_pic`** - Validasi role user

```php
// Contoh rute:
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::resource('users', UserController::class);
});
```

---

## 5. Database Architecture

### Entity Relationship Diagram (Logical)

```
┌─────────────────┐         ┌──────────────────┐
│     users       │         │     schools      │
├─────────────────┤         ├──────────────────┤
│ id (PK)         │         │ id (PK)          │
│ name            │         │ name             │
│ email (UNIQUE)  │         │ address          │
│ password        │         │ pic_name         │
│ role            │         │ created_at       │
│ school_id (FK)  │◄────────│ updated_at       │
│ created_at      │         └──────────────────┘
│ updated_at      │
└─────────────────┘
        ▲
        │ (coach_id)
        │
        │ (admin, coach)
        │
    ┌───────────────────┐       ┌──────────────────┐
    │  coach_classes    │       │    classes       │
    ├───────────────────┤       ├──────────────────┤
    │ id (PK)           │       │ id (PK)          │
    │ coach_id (FK)     │───────│ school_id (FK)   │
    │ class_id (FK)     ├──────►│ name             │
    │ UNIQUE (c,c)      │       │ created_at       │
    └───────────────────┘       │ updated_at       │
                                 └──────────────────┘
                                         ▲
                                         │ (class_id)
                                         │
                                    ┌────────────────┐
                                    │   students     │
                                    ├────────────────┤
                                    │ id (PK)        │
                                    │ class_id (FK)  │
                                    │ name           │
                                    │ created_at     │
                                    └────────────────┘

┌──────────────────────┐
│     reports          │
├──────────────────────┤
│ id (PK)              │
│ coach_id (FK)        │──────► users
│ school_id (FK)       │──────► schools
│ class_id (FK)        │──────► classes
│ report_date          │
│ lesson_material      │
│ activity_summary     │
│ notes                │
│ photo_path           │
│ status (enum)        │ Status: draft, submitted, approved, rejected
│ admin_notes          │
│ approved_by (FK)     │──────► users (admin)
│ approved_at          │
│ created_at           │
│ updated_at           │
└──────────────────────┘
        │
        ├──► ┌──────────────────────┐
        │    │ report_attendances   │
        │    ├──────────────────────┤
        │    │ id (PK)              │
        │    │ report_id (FK)       │
        │    │ student_id (FK)      │
        │    │ status (enum)        │ Status: present, absent, sick, permission
        │    └──────────────────────┘
        │
        └──► ┌──────────────────────┐
             │  report_media        │
             ├──────────────────────┤
             │ id (PK)              │
             │ report_id (FK)       │
             │ type (enum)          │ Type: photo, video
             │ path                 │ URL dari Cloudinary
             │ original_name        │
             │ created_at           │
             │ updated_at           │
             └──────────────────────┘
```

### Cascade Delete
- **reports** DELETED → **report_attendances**, **report_media** auto DELETE
- **classes** DELETED → **students**, **coach_classes**, **reports** auto DELETE
- **schools** DELETED → **classes**, **users** auto DELETE
- **users** DELETED → **coach_classes**, **reports** auto DELETE

---

## 6. Modul/Layer Interaction

```
┌─────────────────────────────────────────────────────────────────┐
│                          ROUTING LAYER                          │
│              (routes/web.php - URL Dispatcher)                 │
└──────────────────┬──────────────────────────────────────────────┘
                   │
    ┌──────────────┼──────────────┬───────────────────┐
    ▼              ▼              ▼                   ▼
 AUTH      ADMIN          COACH             SCHOOL_PIC
 MODULE    MODULE         MODULE              MODULE
    │              │              │                   │
    ├─ Login      ├─ Dashboard   ├─ Dashboard       ├─ Dashboard
    ├─ Logout     ├─ Users       ├─ Reports         ├─ Reports
    │             ├─ Schools     ├─ Attendance      │
    │             ├─ Classes     └─ Media Upload    │
    │             ├─ Coaches                        │
    │             └─ Reports                        │
    │              (Review/Approve)                 │
    │
    └─────────────────────────────────────────────────┘
                        │
    ┌───────────────────┼────────────────────┐
    ▼                   ▼                    ▼
 CONTROLLER      MODEL LAYER          HELPER/SERVICE
 (Request        (Business             - CloudinaryHelper
  Handler)        Logic)               - FastExcel
    │              │                   - Session Mgmt
    │              ├─ User             - Auth Handler
    │              ├─ School
    │              ├─ SchoolClass
    │              ├─ Student
    │              ├─ Report
    │              ├─ ReportAttendance
    │              └─ ReportMedia
    │                   │
    └───────────────────┼─────────────────────────┐
                        ▼                         ▼
                    DATABASE              EXTERNAL SERVICES
                  (MySQL/PG)              - Cloudinary API
                                          - Laravel Queue
```

---

## 7. Data Flow Contoh: Submit Laporan

```
COACH BROWSER
     ↓ (POST /coach/reports)
LARAVEL ROUTE MIDDLEWARE
     ↓ Check: 'auth', 'role:coach'
COACH REPORT CONTROLLER::store()
     ├─ Validate Input
     ├─ Create Report Model
     │  ├─ INSERT: reports table
     │  └─ Set status = 'submitted'
     │
     ├─ Handle Photos
     │  ├─ Loop each photo
     │  ├─ Upload to Cloudinary (via CloudinaryHelper)
     │  ├─ Create ReportMedia row
     │  │  └─ INSERT: report_media (type: 'photo', path: cloudinary_url)
     │
     ├─ Handle Videos
     │  ├─ Loop each video
     │  ├─ Upload to Cloudinary
     │  ├─ Create ReportMedia row
     │  │  └─ INSERT: report_media (type: 'video', path: cloudinary_url)
     │
     ├─ Handle Attendance
     │  ├─ Loop each student
     │  └─ Create ReportAttendance rows
     │     └─ INSERT: report_attendances (student_id, status)
     │
     └─ Redirect to coach.reports.index
        └─ Browser: Success message + List laporan

ADMIN DASHBOARD
     ↓ (GET /admin/dashboard)
ADMIN DASHBOARD CONTROLLER::index()
     ├─ Query: submitted reports (count)
     ├─ Query: pending reports (latest 5)
     └─ Render: admin/dashboard.blade.php
        └─ ADMIN BROWSER: Dashboard + List pending reports

ADMIN REVIEW LAPORAN
     ↓ (GET /admin/reports/{report})
ADMIN REPORT CONTROLLER::show()
     ├─ Load Report with relations:
     │  ├─ coach
     │  ├─ school
     │  ├─ schoolClass
     │  ├─ attendances with students
     │  └─ media (photos + videos)
     └─ Render: admin/reports/show.blade.php
        └─ ADMIN BROWSER: Laporan detail + foto/video

ADMIN APPROVE LAPORAN
     ↓ (PATCH /admin/reports/{report}/approve)
ADMIN REPORT CONTROLLER::approve()
     ├─ Check status = 'submitted'
     ├─ UPDATE reports:
     │  ├─ status = 'approved'
     │  ├─ approved_by = auth()->id() (admin ID)
     │  └─ approved_at = now()
     └─ Redirect: back with success
        └─ Status changed ✓

SCHOOL PIC DASHBOARD
     ↓ (GET /pic/dashboard)
PIC DASHBOARD CONTROLLER::index()
     ├─ Get school_id from auth()->user()->school_id
     ├─ Query: approved reports WHERE school_id = ... AND status = 'approved'
     ├─ Load relations: schoolClass, coach
     └─ Render: school_pic/dashboard.blade.php
        └─ SCHOOL PIC BROWSER: Laporan tersetujui
```

---

## 8. Security Architecture

### Authentication Layer
- Session-based authentication
- Password hashing dengan BCrypt
- Session regeneration setelah login

### Authorization Layer
- **RoleMiddleware**: Check user role
- **Resource Authorization**: Controller checks model ownership

```php
// Contoh: Coach hanya bisa edit laporan miliknya
public function edit(Report $report)
{
    abort_if($report->coach_id !== Auth::id(), 403);
    abort_if(!in_array($report->status, ['draft', 'rejected']), 403);
}
```

### Data Protection
- Foreign key constraints dengan cascade delete
- Database transactions untuk consistency
- Input validation dan sanitization

---

## 9. Performance Considerations

### Query Optimization
- Eager loading dengan `load()` / `with()`
- Pagination untuk list data (20-15 items per page)
- Indexes pada foreign keys

### Caching Strategy
- Session berbasis database
- Cache store dapat dikonfigurasi

### File Upload Strategy
- Menggunakan Cloudinary (tidak simpan lokal)
- URL Cloudinary disimpan di database
- Async upload possible dengan queue

---

## 10. Deployment Architecture

```
┌────────────────────────────────┐
│    Developer Local Machine     │
│  (SQLite, Composer, NPM)       │
└────────────┬───────────────────┘
             │ git push
             ▼
┌────────────────────────────────┐
│  GitHub Repository             │
└────────────┬───────────────────┘
             │
             ▼ (Docker Build)
┌────────────────────────────────┐
│  Docker Container              │
│  - PHP 8.3                     │
│  - Composer deps               │
│  - App running on :8080        │
└────────────┬───────────────────┘
             │
             ▼ (Deploy to)
┌────────────────────────────────┐
│  Railway Platform              │
│  - Managed DB (MySQL/PG)       │
│  - Custom Domain               │
│  - Environment vars            │
│  - Auto-restart                │
└────────────────────────────────┘
             │
             ▼
┌────────────────────────────────┐
│  External: Cloudinary          │
│  - Media Storage               │
│  - URL CDN                     │
└────────────────────────────────┘
```

