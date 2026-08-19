# 📁 Penjelasan Struktur Folder

## 1. Root Level Structure

```
learning-report-system/
├── app/                          # Inti aplikasi (Models, Controllers, Middleware, dll)
├── bootstrap/                    # Bootstrap aplikasi
├── config/                       # File konfigurasi
├── database/                     # Migrations, Seeders, Factories
├── resources/                    # Views, CSS, JS (raw)
├── routes/                       # Route definitions
├── public/                       # Web root (accessible via browser)
├── storage/                      # File storage (logs, cache, sessions, uploads)
├── tests/                        # Unit & Feature tests
├── vendor/                       # Composer packages (gitignored)
├── node_modules/                # NPM packages (gitignored)
├── .env                          # Environment variables (gitignored)
├── .env.example                  # Template environment
├── .gitignore                    # Git ignore rules
├── artisan                       # Artisan CLI tool
├── composer.json                 # Composer dependencies
├── composer.lock                 # Locked composer versions
├── package.json                  # NPM dependencies
├── vite.config.js                # Vite bundler config
├── phpunit.xml                   # PHPUnit config
├── Dockerfile                    # Docker configuration
├── railway.env.example           # Railway environment template
├── README.md                     # Project readme
├── DEVELOPER_GUIDE.md            # Developer guide
├── DEPLOYMENT_GUIDE.md           # Deployment guide
└── docs/                         # Documentation (THIS FOLDER)
```

---

## 2. Folder Detail: `app/`

### `app/Models/`
**Tujuan**: Eloquent Models (data entities)

```
Models/
├── User.php              # User model (superadmin, relation, coach, dll. Termasuk teacher_school & finance)
├── School.php            # School/Sekolah model
├── SchoolClass.php       # Class/Kelas model (tabel: classes)
├── Student.php           # Student/Siswa model
├── CoachClass.php        # Many-to-many: Coach ↔ Class
├── Report.php            # Learning Report model
├── ReportAttendance.php  # Student attendance in report
└── ReportMedia.php       # Report photos/videos
```

**Masing-masing file**:
- Define `$fillable` (mass assignable attributes)
- Define relationships dengan model lain
- Define `$casts` untuk type casting
- Helper methods untuk business logic

---

### `app/Http/Controllers/`
**Tujuan**: Request handlers

```
Controllers/
├── Controller.php                # Base controller class
├── Auth/
│   └── LoginController.php       # Login/logout handling
├── Admin/
│   ├── DashboardController.php   # Admin dashboard
│   ├── UserController.php        # User CRUD
│   ├── SchoolController.php      # School CRUD
│   ├── ClassController.php       # Class CRUD
│   ├── CoachController.php       # Coach management
│   └── ReportController.php      # Report review & approval
├── Coach/
│   └── ReportController.php      # Coach report CRUD
├── SchoolPic/
│   └── DashboardController.php   # PIC dashboard
└── StudentController.php         # Student CRUD (multi-role)
```

**Pattern**: 
- Each controller handles one resource
- Actions: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`
- Authorization checks di awal method

---

### `app/Http/Middleware/`
**Tujuan**: Request filtering/processing

```
Middleware/
└── RoleMiddleware.php    # Check user role
```

**Usage**: 
```php
Route::middleware('role:admin')->group(...);
```

---

### `app/Helpers/`
**Tujuan**: Utility functions

```
Helpers/
├── CloudinaryHelper.php  # Upload/delete files ke Cloudinary
└── [Custom helpers]
```

---

### `app/Providers/`
**Tujuan**: Service providers

```
Providers/
├── AppServiceProvider.php       # App service provider
└── [Other providers]
```

---

## 3. Folder Detail: `database/`

### `database/migrations/`
**Tujuan**: Database schema definitions

```
migrations/
├── 2026_02_28_214640_create_schools_table.php
├── 2026_02_28_214726_create_users_table.php
├── 2026_02_28_214748_create_classes_table.php
├── 2026_02_28_215654_create_students_table.php
├── 2026_02_28_215718_create_coach_classes_table.php
├── 2026_02_28_215822_create_reports_table.php
├── 2026_02_28_215901_create_report_attendances_table.php
├── 2026_02_28_230827_create_sessions_table.php
└── 2026_03_01_215607_create_report_media_table.php
```

**Convention**: 
- Nama file: `YYYY_MM_DD_HHMMSS_action_table_name.php`
- Tanggal = waktu migration dibuat
- Action = `create`, `alter`, `add`, dll

---

### `database/seeders/`
**Tujuan**: Populate database dengan data awal

```
seeders/
├── DatabaseSeeder.php    # Main seeder
└── [Other seeders]
```

---

### `database/factories/`
**Tujuan**: Generate fake data untuk testing

```
factories/
└── UserFactory.php       # Generate fake User records
```

---

## 4. Folder Detail: `resources/`

### `resources/views/`
**Tujuan**: Blade templates (view layer)

```
views/
├── auth/
│   └── login.blade.php              # Login form
├── admin/
│   ├── dashboard.blade.php          # Admin dashboard
│   ├── users/
│   │   ├── index.blade.php          # User list
│   │   ├── create.blade.php         # Create user form
│   │   └── edit.blade.php           # Edit user form
│   ├── master/
│   │   ├── schools.blade.php        # Schools CRUD
│   │   ├── classes.blade.php        # Classes CRUD
│   │   ├── coaches.blade.php        # Coaches list
│   │   └── coach_show.blade.php     # Coach detail + assign
│   └── reports/
│       ├── index.blade.php          # Reports list
│       └── show.blade.php           # Report detail + approve/reject
├── coach/
│   └── reports/
│       ├── index.blade.php          # Laporan list
│       ├── create.blade.php         # Buat laporan
│       └── edit.blade.php           # Edit laporan
├── school_pic/
│   └── dashboard.blade.php          # PIC dashboard
├── students/
│   └── index.blade.php              # Students list + add/import
└── layouts/
    └── app.blade.php                # Master layout (header, nav, footer)
```

**Blade**: Template engine Laravel
- Syntax: `{{ $variable }}`, `@if`, `@foreach`, dll
- Auto-escaped untuk security
- Include: `@include('partials.header')`

---

### `resources/css/`
**Tujuan**: CSS styles

```
css/
└── app.css              # Global styles (compiled via Vite)
```

---

### `resources/js/`
**Tujuan**: JavaScript files

```
js/
├── app.js               # Entry point
├── bootstrap.js         # Bootstrap (include axios, etc)
└── [Custom JS files]
```

---

## 5. Folder Detail: `config/`

### Configuration Files

```
config/
├── app.php              # App config (name, timezone, providers, dll)
├── auth.php             # Authentication config
├── cache.php            # Cache configuration
├── database.php         # Database connection settings
├── filesystems.php      # File storage config
├── logging.php          # Logging configuration
├── mail.php             # Mail configuration
├── queue.php            # Queue configuration
├── services.php         # External services (Cloudinary, etc)
├── session.php          # Session configuration
└── [Other configs]
```

**services.php**: 
```php
'cloudinary' => [
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key'    => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),
]
```

---

## 6. Folder Detail: `routes/`

### Route Definitions

```
routes/
├── web.php              # Web routes (HTML responses)
└── console.php          # Artisan commands (not typical routes)
```

**web.php**: Semua HTTP routes di sini
- Public routes (login)
- Auth routes (protected)
- Role-based routes (prefix: /admin, /coach, /pic)
- AJAX API endpoints

---

## 7. Folder Detail: `public/`

### Web Root

```
public/
├── index.php            # Entry point (important!)
├── robots.txt           # SEO
├── css/                 # Compiled CSS
├── js/                  # Compiled JS
├── images/              # Static images
└── storage/             # Symlink to storage/app/public (OPTIONAL)
```

**Important**: 
- Hanya file di `/public` yang accessible via browser
- Media files sebenarnya di Cloudinary (bukan local storage)
- `/public/storage` adalah symlink (jika digunakan)

---

## 8. Folder Detail: `storage/`

### Application Storage

```
storage/
├── app/
│   ├── public/          # Public files (symlinked to public/storage)
│   └── private/         # Private files
├── framework/
│   ├── cache/           # Framework cache
│   ├── sessions/        # Session data (DB-driven)
│   ├── views/           # Compiled views
│   └── testing/         # Test data
├── logs/                # Application logs
│   └── laravel.log      # Main log file
└── [Other storage]
```

---

## 9. Folder Detail: `tests/`

### Automated Tests

```
tests/
├── TestCase.php         # Base test class
├── Feature/
│   └── ExampleTest.php  # Feature tests (integration)
└── Unit/
    └── ExampleTest.php  # Unit tests
```

---

## 10. Important Files at Root

### `.env`
```
Environment variables:
- APP_NAME
- APP_ENV (local, production)
- APP_DEBUG
- APP_KEY (encryption key)
- DB_* (database credentials)
- CLOUDINARY_* (API keys)
- SESSION_DRIVER
- CACHE_STORE
- QUEUE_CONNECTION
```

**Note**: `.env` is gitignored (security), use `.env.example` as template

---

### `composer.json`
```
Dependencies:
- laravel/framework ^12.0
- cloudinary/cloudinary_php ^3.1
- rap2hpoutre/fast-excel ^5.6
- doctrine/dbal ^4.4

Scripts:
- composer setup (full setup)
- composer dev (development server)
- composer test (run tests)
```

---

### `package.json`
```
NPM dependencies:
- (Currently: "No build step required")

Scripts:
- npm run build (compile assets)
- npm run dev (watch mode, tidak digunakan sekarang)
```

---

### `vite.config.js`
```
Vite configuration:
- Asset bundling
- CSS/JS compilation
- Hot reload (dev)
```

---

### `Dockerfile`
```
Docker image definition:
- PHP 8.3 CLI
- Extensions: PDO, gd, xml, dll
- Composer install
- Storage permissions setup
- Expose port 8080
- Run: php artisan serve
```

---

## 11. Folder Access Permissions

### Important Permissions

```
storage/             → 775 (readable, writable by app)
bootstrap/cache/     → 775
public/              → 755
public/storage/      → 755 (symlink)
```

**Issue**: 
- Wrong permissions → app can't write logs
- Solution: `chmod -R 775 storage bootstrap/cache`

---

## 12. .gitignore Key Entries

```
.env                 # Environment variables
vendor/              # Composer packages
node_modules/        # NPM packages
storage/logs/        # Log files
storage/framework/   # Framework cache
public/storage/      # Symlink to storage/app/public
.DS_Store            # macOS files
```

---

## 13. File Organization Best Practices

### Naming Conventions

**Controllers**: Singular + \"Controller\"
```
UserController.php (NOT UsersController)
ReportController.php
```

**Models**: Singular + Capitalized
```
User.php (NOT Users.php)
Report.php
```

**Views**: Lowercase + underscore
```
admin/users/index.blade.php
admin/reports/show.blade.php
```

**Routes**: Resource routes (CRUD standard)
```
GET    /resource            → index
GET    /resource/create     → create
POST   /resource            → store
GET    /resource/{id}       → show
GET    /resource/{id}/edit  → edit
PUT    /resource/{id}       → update
DELETE /resource/{id}       → destroy
```

---

## 14. Adding New Features (File Structure)

### Contoh: Tambah fitur \"Analytics\"

**Files needed**:
```
app/Models/Analytics.php          # Model (jika perlu)
app/Http/Controllers/Admin/AnalyticsController.php  # Controller
resources/views/admin/analytics/  # Views
  ├── index.blade.php
  └── show.blade.php
routes/web.php                    # Add route
database/migrations/2026_xx_xx_*_create_analytics_table.php  # Migration
```

**Execution**:
1. Create migration → `php artisan make:migration create_analytics_table`
2. Create model → `php artisan make:model Analytics`
3. Create controller → `php artisan make:controller Admin/AnalyticsController`
4. Add routes → Edit `routes/web.php`
5. Create views → Add blade files
6. Run migration → `php artisan migrate`

