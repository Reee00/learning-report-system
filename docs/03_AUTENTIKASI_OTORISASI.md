# 🔐 Autentikasi & Otorisasi

## 1. Sistem Autentikasi

### 1.1 Tipe Autentikasi
- **Tipe**: Session-based authentication (stateful)
- **Framework**: Laravel built-in authentication
- **Driver**: Database session driver
- **Password Hash**: Bcrypt

---

## 2. Login Flow

### Langkah-langkah Proses Login

```
START
  ↓
[User access /login]
  ├─ Jika sudah login → Redirect ke dashboard sesuai role
  └─ Jika belum → Tampilkan form login
  ↓
[User submit form login]
  ├─ POST /login dengan email & password
  ↓
[Validasi Input]
  ├─ Email required, format email
  ├─ Password required
  └─ Jika invalid → Kembali ke form + error message
  ↓
[Cek Kredensial]
  ├─ Query: SELECT * FROM users WHERE email = [input_email]
  ├─ Bandingkan password (Bcrypt compare)
  └─ Jika tidak match → Error 'Email atau password salah'
  ↓
[Login Berhasil]
  ├─ Session created
  ├─ User ID disimpan di session
  ├─ Session ID disimpan di cookie
  ├─ Session data disimpan di database
  └─ Regenerate session ID (security)
  ↓
[Redirect]
  ├─ IF role = admin → /admin/dashboard
  ├─ IF role = coach → /coach/reports
  └─ IF role = school_pic → /pic/dashboard
  ↓
END
```

### Code Implementation (LoginController)

```php
class LoginController extends Controller
{
    // Tampilkan form login
    public function showForm()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user()->role);
        }
        return view('auth.login');
    }

    // Proses login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return $this->redirectByRole(Auth::user()->role);
        }

        return back()
            ->withErrors(['email' => 'Email atau password salah.'])
            ->onlyInput('email');
    }

    // Redirect berdasarkan role
    private function redirectByRole(string $role)
    {
        return match ($role) {
            'admin'      => redirect()->route('admin.dashboard'),
            'coach'      => redirect()->route('coach.reports.index'),
            'school_pic' => redirect()->route('pic.dashboard'),
            default      => redirect('/'),
        };
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
```

---

## 3. Session Management

### Session Configuration

**File**: `config/session.php`

```
SESSION_DRIVER=database
SESSION_LIFETIME=120 (menit)
SESSION_COOKIE_NAME=LARAVEL_SESSION
SESSION_COOKIE_SECURE=true (production)
SESSION_COOKIE_HTTP_ONLY=true
SESSION_COOKIE_SAME_SITE=lax
```

### Session Storage
- **Database Table**: `sessions` (auto-created via migration)
- **Session Data Stored**: 
  - User ID
  - User role
  - CSRF token
  - Flash data (error messages, success messages)

### Session Lifecycle

```
┌─────────────────────────────────────────────┐
│ Session dimulai saat login                  │
├─────────────────────────────────────────────┤
│ Browser: Menerima cookie (LARAVEL_SESSION)  │
├─────────────────────────────────────────────┤
│ Setiap request:                             │
│ ├─ Browser kirim cookie                     │
│ ├─ Laravel lookup session di database       │
│ ├─ Load user dari session                   │
│ └─ Tersedia via Auth::user()                │
├─────────────────────────────────────────────┤
│ Saat logout:                                │
│ ├─ Session dihapus dari database            │
│ ├─ Cookie dihapus dari browser              │
│ └─ Token di-regenerate                      │
├─────────────────────────────────────────────┤
│ Saat timeout (120 menit):                   │
│ ├─ Session expired                          │
│ ├─ User redirect ke /login                  │
│ └─ User harus login ulang                   │
└─────────────────────────────────────────────┘
```

---

## 4. Authorization (Otorisasi)

### Role-Based Access Control (RBAC)

**3 Role dalam Sistem**:

| Role | Deskripsi | Access |
|------|-----------|--------|
| **admin** | Administrator sistem | Full access |
| **coach** | Pengajar/Instruktur | Create & manage laporan, view assigned classes |
| **school_pic** | Penanggung Jawab Institusi | View approved reports dari sekolahnya |

### RoleMiddleware

**File**: `app/Http/Middleware/RoleMiddleware.php`

```php
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        // Check: User sudah login?
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // Check: Role user cocok?
        if (!in_array(Auth::user()->role, $roles)) {
            abort(403, 'Akses tidak diizinkan.');
        }

        return $next($request);
    }
}
```

### Middleware Registration

**File**: `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ]);
})
```

### Route Protection

**File**: `routes/web.php`

```php
// Public routes
Route::get('/login',  [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

// Admin routes (role check)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('dashboard', [AdminDashboard::class, 'index']);
    Route::resource('users', UserController::class);
});

// Coach routes (role check)
Route::middleware(['auth', 'role:coach'])->prefix('coach')->group(function () {
    Route::resource('reports', CoachReportController::class);
});

// School PIC routes (role check)
Route::middleware(['auth', 'role:school_pic'])->prefix('pic')->group(function () {
    Route::get('dashboard', [PicDashboard::class, 'index']);
});

// Multiple roles
Route::middleware(['auth', 'role:admin,coach'])->group(function () {
    // Both admin and coach can access
});
```

---

## 5. Authorization Checks di Controller Level

### Check Role
```php
// Di controller:
if (Auth::user()->role !== 'admin') {
    abort(403);
}
```

### Check Resource Ownership
```php
// Coach hanya bisa edit laporan miliknya
public function edit(Report $report)
{
    abort_if($report->coach_id !== Auth::id(), 403);
    // ... rest of code
}

// Admin hanya bisa reject laporan dengan status submitted
public function reject(Report $report)
{
    abort_if($report->status !== 'submitted', 422);
    // ... rest of code
}
```

### School PIC Filter
```php
// School PIC hanya lihat laporan dari sekolahnya
public function index(Request $request)
{
    $schoolId = Auth::user()->school_id;
    
    $reports = Report::where('school_id', $schoolId)
        ->where('status', 'approved')
        ->get();
}
```

---

## 6. Password Management

### Password Creation
```php
// Saat create user (admin):
$user = User::create([
    'email'    => $request->email,
    'password' => Hash::make($request->password),
    'role'     => 'coach',
]);
```

### Password Reset
```php
// Admin reset password user:
public function resetPassword(Request $request, User $user)
{
    $request->validate([
        'password' => 'required|string|min:6|confirmed',
    ]);

    $user->update([
        'password' => Hash::make($request->password),
    ]);
}
```

### Password Hashing Details
- **Algorithm**: Bcrypt
- **Work Factor**: 10 (default Laravel)
- **Salted**: Automatically
- **Comparison**: Timing-safe (protection against timing attacks)

---

## 7. CSRF Protection

### Automatic Protection
Laravel automatically adds CSRF protection ke semua forms.

```html
<!-- Automatic CSRF token field -->
<form method="POST" action="/login">
    @csrf
    <!-- Form fields -->
</form>
```

### CSRF Token Verification
- Token divalidasi di middleware stack
- Token di-regenerate setiap request (configurable)
- Token disimpan di session
- Request POST/PUT/DELETE harus include CSRF token

---

## 8. Access Control Summary

### Admin Access
```
✓ GET  /admin/dashboard
✓ GET  /admin/users
✓ POST /admin/users (create)
✓ PUT  /admin/users/{user} (update)
✓ DELETE /admin/users/{user}
✓ GET  /admin/reports
✓ PATCH /admin/reports/{report}/approve
✓ PATCH /admin/reports/{report}/reject
✓ GET  /admin/schools
✓ POST /admin/schools
✓ GET  /admin/classes
✓ GET  /admin/coaches
```

### Coach Access
```
✓ GET  /coach/reports (list)
✓ POST /coach/reports (create)
✓ GET  /coach/reports/{report}/edit
✓ PUT  /coach/reports/{report} (update)
✓ GET  /classes/{class}/students (list students di kelas assign)
✓ POST /classes/{class}/students (add student)
✓ POST /classes/{class}/students/import (import dari Excel)
✓ DELETE /classes/{class}/students/{student}
```

### School PIC Access
```
✓ GET /pic/dashboard
✓ GET /pic/reports/{report} (hanya approved reports)
```

### Public (Not Authenticated)
```
✓ GET /login
✓ POST /login
```

---

## 9. Security Best Practices

### Implemented
- ✅ Password hashing dengan Bcrypt
- ✅ Session-based authentication
- ✅ CSRF token protection
- ✅ Role-based access control
- ✅ HTTP-only cookies
- ✅ Session regeneration after login
- ✅ Secure password reset (admin-initiated)
- ✅ Resource ownership checks
- ✅ Input validation & sanitization

### Recommended Additions
- ⚠️ Implement rate limiting untuk login (prevent brute force)
- ⚠️ Add logging untuk failed login attempts
- ⚠️ Implement 2FA (Two-Factor Authentication)
- ⚠️ Add password expiry policy
- ⚠️ Add user activity logging
- ⚠️ Implement API token authentication (jika ada API public)

---

## 10. Error Handling

### Authentication Errors
```
401 Unauthenticated
→ Redirect ke /login
→ Flash message: \"Silakan login terlebih dahulu\"
```

### Authorization Errors
```
403 Forbidden
→ Abort dengan pesan: \"Akses tidak diizinkan\"
→ Menampilkan error 403 page
```

### Validation Errors
```
422 Unprocessable Entity
→ Redirect back ke form
→ Flash errors ke session
→ Re-populate form fields (except password)
```

---

## 11. Testing Authentication

### Manual Testing Steps
```
1. Test Login Valid Credentials
   - Go to /login
   - Enter valid email & password
   - Expect: Redirect to dashboard sesuai role

2. Test Login Invalid Credentials
   - Go to /login
   - Enter wrong password
   - Expect: Error message, back to login form

3. Test Access Protected Route (not authenticated)
   - Clear session/cookies
   - Direct access to /admin/dashboard
   - Expect: Redirect to /login

4. Test Role-Based Access
   - Login as coach
   - Try access /admin/dashboard
   - Expect: 403 Forbidden error

5. Test Logout
   - Login successfully
   - Click Logout
   - Try access /admin/dashboard
   - Expect: Redirect to /login

6. Test Session Timeout
   - Login
   - Wait 120+ minutes
   - Try any request
   - Expect: Session expired, redirect to login
```

