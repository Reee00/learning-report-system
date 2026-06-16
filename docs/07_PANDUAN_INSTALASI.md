# 🚀 Panduan Instalasi & Setup

## 1. Prerequisites (Persyaratan)

### Untuk Local Development
- **PHP**: 8.2 atau lebih tinggi (8.3 recommended)
- **Composer**: Latest version (untuk manage PHP dependencies)
- **Node.js & NPM**: Terbaru (untuk manage JavaScript dependencies)
- **Database**: 
  - SQLite (default, built-in, tidak perlu setup)
  - ATAU MySQL 5.7+
  - ATAU PostgreSQL 10+
- **Git**: Untuk version control

### Untuk Production
- **Docker**: Untuk containerization
- **Docker Compose**: Untuk orchestration (optional)
- **Database Server**: MySQL/PostgreSQL managed
- **Web Server**: Nginx atau Apache (or use Docker built-in)
- **Cloudinary Account**: Untuk media storage

---

## 2. Installation Steps (Local Development)

### 2.1 Clone Repository

```bash
# Clone dari GitHub
git clone https://github.com/Reee00/learning-report-system.git

# Masuk ke folder
cd learning-report-system
```

### 2.2 Copy Environment File

```bash
# Copy template ke .env
cp .env.example .env

# ATAU manual copy jika tidak ada command di Windows
# Copy file .env.example, paste, rename ke .env
```

### 2.3 Install Dependencies

#### Option A: Menggunakan composer setup script (RECOMMENDED)

```bash
composer setup
```

**Script ini akan otomatis:**
1. `composer install` - Install PHP dependencies
2. Copy `.env.example` → `.env`
3. Generate `APP_KEY`
4. Run database migration
5. `npm install` - Install JavaScript dependencies
6. `npm run build` - Build assets

#### Option B: Manual Step-by-Step

```bash
# 1. Install Composer dependencies
composer install

# 2. Generate APP_KEY
php artisan key:generate

# 3. Create database.sqlite
touch database/database.sqlite

# 4. Run migrations
php artisan migrate

# 5. Seed database (optional, creates sample data)
php artisan db:seed

# 6. Install NPM dependencies
npm install

# 7. Build assets
npm run build

# 8. Create storage symlink (untuk public file access)
php artisan storage:link
```

### 2.4 Start Development Server

#### Using Artisan (Simple)

```bash
php artisan serve
```

**Output**:
```
Laravel development server started:
http://127.0.0.1:8000
```

#### Using Composer dev script (Recommended)

```bash
composer dev
```

**Script ini akan jalankan:**
- `php artisan serve` (port 8000)
- `php artisan queue:listen` (queue worker)
- `php artisan pail` (log viewer)
- `npm run dev` (Vite watch mode)

**Keuntungan**: Semua process jalan parallel dengan pretty output

### 2.5 Access Application

Open browser: `http://localhost:8000`

---

## 3. Environment Configuration

### 3.1 Edit `.env` File

```env
# Application
APP_NAME="Learning Report System"
APP_ENV=local
APP_DEBUG=true
APP_KEY=base64:YOUR_APP_KEY_HERE  (generated automatically)
APP_URL=http://localhost:8000

# Database (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=/full/path/to/database.sqlite

# OR Database (MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=learning_report_system
DB_USERNAME=root
DB_PASSWORD=

# OR Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=learning_report_system
DB_USERNAME=postgres
DB_PASSWORD=

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Cache
CACHE_STORE=database

# Queue
QUEUE_CONNECTION=database

# Cloudinary (untuk media upload)
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
```

### 3.2 Generate Cloudinary Credentials

1. Go to: https://cloudinary.com
2. Sign up untuk free account
3. Dapatkan credentials dari dashboard:
   - Cloud Name
   - API Key
   - API Secret
4. Paste ke `.env`

### 3.3 Database Setup (MySQL example)

```bash
# 1. Create database
mysql -u root -p -e "CREATE DATABASE learning_report_system;"

# 2. Update .env dengan credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=learning_report_system
DB_USERNAME=root
DB_PASSWORD=your_password

# 3. Run migrations
php artisan migrate
```

---

## 4. Database Seeding (Sample Data)

### Create Initial Admin User

```bash
# Run seeder
php artisan db:seed

# OR specific seeder
php artisan db:seed --class=DatabaseSeeder
```

**Default test users** (jika ada di seeder):
```
Email: admin@example.com
Password: password

Email: coach@example.com
Password: password

Email: pic@example.com
Password: password
```

**Note**: Passwords di seeder biasanya hashed, lihat `DatabaseSeeder.php` untuk detailnya.

### Manual Add Admin User

Jika tidak ada seeder atau ingin buat manual:

```bash
# Via Artisan Tinker (REPL)
php artisan tinker

# Di Tinker shell:
$user = \App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => Hash::make('password123'),
    'role' => 'admin',
    'school_id' => null,
]);
```

---

## 5. Verification Checklist

### Cek Instalasi Berhasil

```bash
# 1. Cek PHP version
php --version
# Output: PHP 8.x.x or higher ✓

# 2. Cek dependencies terpasang
php artisan --version
# Output: Laravel Framework 12.x ✓

# 3. Cek database connection
php artisan tinker
# Di Tinker: \DB::connection()->getPdo()
# Output: PDOConnection ✓

# 4. Cek migration
php artisan migrate:status
# Output: Batches dengan status Ran ✓

# 5. Cek application key
cat .env | grep APP_KEY
# Output: APP_KEY=base64:... (not empty) ✓
```

### Test Login

1. Go to: `http://localhost:8000/login`
2. Enter email & password (dari seeder)
3. Click Login
4. Should redirect ke dashboard

---

## 6. Common Issues & Solutions

### Issue 1: \"No application encryption key has been specified\"

**Cause**: APP_KEY not generated

**Solution**:
```bash
php artisan key:generate
```

### Issue 2: \"SQLSTATE[HY000]: General error: 1 table users already exists\"

**Cause**: Migrations already ran, or database conflict

**Solution**:
```bash
# Option A: Fresh start (WARNING: deletes all data)
php artisan migrate:fresh

# Option B: Check current migrations
php artisan migrate:status

# Option C: Reset specific database (if using SQLite)
rm database/database.sqlite
php artisan migrate
```

### Issue 3: \"CORS error\" atau \"Connection refused\"

**Cause**: Server tidak running atau wrong URL

**Solution**:
```bash
# Check if server running
php artisan serve

# Or check if on correct port
# Default: http://localhost:8000 (not 127.0.0.1:8000)
```

### Issue 4: \"Class 'Cloudinary\\Cloudinary' not found\"

**Cause**: Cloudinary package not installed

**Solution**:
```bash
composer require cloudinary/cloudinary_php
```

### Issue 5: \"CLOUDINARY credentials not set\"

**Cause**: `.env` file not configured with Cloudinary keys

**Solution**:
1. Get Cloudinary credentials dari https://cloudinary.com
2. Add to `.env`:
```env
CLOUDINARY_CLOUD_NAME=xxx
CLOUDINARY_API_KEY=xxx
CLOUDINARY_API_SECRET=xxx
```
3. Clear config cache:
```bash
php artisan config:clear
```

### Issue 6: \"Permission denied\" untuk storage/logs

**Cause**: Folder permissions terlalu restrictive

**Solution**:
```bash
# Linux/Mac
chmod -R 775 storage bootstrap/cache

# Windows (PowerShell, run as admin)
icacls "storage" /grant:r "%USERNAME%:F" /T
icacls "bootstrap/cache" /grant:r "%USERNAME%:F" /T
```

---

## 7. Development Commands

### Useful Artisan Commands

```bash
# Database
php artisan migrate               # Run pending migrations
php artisan migrate:fresh         # Reset & re-run all migrations
php artisan migrate:rollback      # Rollback last batch
php artisan db:seed               # Run seeders
php artisan tinker                # Interactive shell (REPL)

# Cache & Config
php artisan config:cache          # Cache configuration
php artisan config:clear          # Clear config cache
php artisan cache:clear           # Clear all caches
php artisan view:clear            # Clear compiled views

# Storage
php artisan storage:link          # Create storage symlink

# Testing
php artisan test                  # Run PHPUnit tests
php artisan test --filter=TestName

# Serving
php artisan serve                 # Start development server (port 8000)
php artisan serve --port=8080     # Custom port

# Optimization
php artisan optimize              # Optimize application
php artisan optimize:clear        # Clear optimizations

# Queue (if using)
php artisan queue:listen          # Listen for queue jobs
```

---

## 8. First-Time Setup Walkthrough

### Step 1: Installation (5 min)
```bash
git clone https://github.com/Reee00/learning-report-system.git
cd learning-report-system
composer setup
```

### Step 2: Configuration (3 min)
```bash
# Edit .env if needed (Cloudinary optional for local dev)
# Default SQLite database auto-created
```

### Step 3: Start Server (1 min)
```bash
composer dev
```

### Step 4: Access App (1 min)
```
Browser: http://localhost:8000
Login: admin@example.com / password (dari seeder)
```

### Step 5: Test Workflow (5 min)
- Login as admin
- Create school + class
- Create coach user
- Assign coach to class
- Add students
- Switch role: login as coach
- Create report
- Switch role: login as admin
- Review & approve report
- Switch role: login as PIC
- View approved reports

---

## 9. Development Workflow

### Daily Setup
```bash
# 1. Start terminal
cd learning-report-system

# 2. Start dev server (all-in-one)
composer dev

# This runs:
# - Laravel server (port 8000)
# - Queue listener
# - Pail (logs)
# - Vite bundler (assets)

# 3. Open browser
# http://localhost:8000
```

### Making Changes
```bash
# 1. Edit PHP code (auto-reloaded by Laravel)
# 2. Edit CSS/JS (auto-compiled by Vite)
# 3. Edit blade templates (auto-reloaded)

# If something breaks:
php artisan cache:clear
php artisan config:clear
```

### Database Changes
```bash
# 1. Create new migration
php artisan make:migration add_column_to_users_table

# 2. Edit database/migrations/[filename].php

# 3. Run migration
php artisan migrate

# 4. If wrong, rollback
php artisan migrate:rollback
```

### Testing Changes
```bash
# Run tests
php artisan test

# Run specific test
php artisan test tests/Feature/LoginTest.php

# Run with coverage
php artisan test --coverage
```

---

## 10. Git Workflow

### Before Starting Work

```bash
# Update from remote
git pull origin main

# Create feature branch
git checkout -b feature/add-analytics

# Make changes
# ... edit files ...

# Stage changes
git add .

# Commit
git commit -m \"Add analytics feature\"

# Push
git push origin feature/add-analytics

# Create Pull Request di GitHub
```

### After Pulling New Code

```bash
# Update dependencies
composer install
npm install

# Run migrations
php artisan migrate

# Clear caches
php artisan cache:clear
php artisan config:clear
```

---

## 11. Troubleshooting Installation

### Complete Fresh Start

```bash
# 1. Delete generated files
rm -rf vendor/
rm -rf node_modules/
rm database/database.sqlite
rm .env

# 2. Reinstall
cp .env.example .env
composer install
npm install
php artisan key:generate

# 3. Setup database
php artisan migrate
php artisan db:seed

# 4. Start server
php artisan serve
```

### Verify Everything Works

```bash
# 1. Check PHP
php --version

# 2. Check Composer
composer --version

# 3. Check Node
node --version
npm --version

# 4. Check Laravel
php artisan --version

# 5. Check Database
php artisan migrate:status

# 6. Test server
php artisan serve
# Visit: http://localhost:8000/login
```

