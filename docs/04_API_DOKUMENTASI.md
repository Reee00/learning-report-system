# 📡 API Documentation

## 1. Overview API

Sistem ini menggunakan **web routing** dengan Blade templates. Tidak ada REST API pure, namun terdapat AJAX endpoint untuk keperluan interaktif.

**Base URL**: 
```
Local: http://localhost:8000
Production: https://[domain]
```

---

## 2. Authentication

Semua endpoint memerlukan user login (session-based).

```
Status: 401 Unauthorized
Response: Redirect ke /login
```

---

## 3. List Semua Endpoints

### A. Authentication Endpoints

#### GET /login
**Deskripsi**: Tampilkan form login

**Request**: 
- Method: `GET`
- Auth: None

**Response**:
```html
<!-- Form login dengan email & password -->
```

**Status Code**: `200 OK`

---

#### POST /login
**Deskripsi**: Proses login

**Request**:
```
Method: POST
Headers: Content-Type: application/x-www-form-urlencoded
Body:
  email: string (required, email format)
  password: string (required)
  _token: string (CSRF token, auto)
```

**Response - Success**:
```
Status: 302 Found (Redirect)
Location: 
  - /admin/dashboard (jika admin)
  - /coach/reports (jika coach)
  - /pic/dashboard (jika school_pic)
```

**Response - Error**:
```
Status: 302 Found (Back)
Flash: 
  - withErrors(['email' => 'Email atau password salah.'])
  - onlyInput('email')
```

**Example**:
```bash
curl -X POST http://localhost:8000/login \
  -d "email=coach@example.com&password=password123&_token=..." \
  -c cookies.txt
```

---

#### POST /logout
**Deskripsi**: Logout (keluar dari session)

**Request**:
```
Method: POST
Auth: Required (session)
Headers: Content-Type: application/x-www-form-urlencoded
Body:
  _token: string (CSRF token)
```

**Response**:
```
Status: 302 Found (Redirect)
Location: /login
Result: Session invalidated, cookies cleared
```

---

### B. Admin Endpoints

#### GET /admin/dashboard
**Deskripsi**: Dashboard admin dengan statistik

**Auth**: `role:admin`

**Response Body**:
```html
<!-- Dashboard dengan:
  - total_reports
  - submitted_reports
  - approved_reports
  - rejected_reports
  - total_schools
  - total_coaches
  - pending reports (5 terbaru)
-->
```

**Status Code**: `200 OK`

---

#### GET /admin/users
**Deskripsi**: List semua user (Admin, Coach, School PIC)

**Auth**: `role:admin`

**Query Parameters**:
```
role=admin|coach|school_pic (optional)
search=string (optional, search by name/email)
page=number (optional, default 1)
```

**Response**:
```html
<!-- Tabel user dengan:
  - name
  - email
  - role
  - school (jika school_pic)
  - Actions: Edit, Reset Password, Delete
-->
```

**Status Code**: `200 OK`

---

#### POST /admin/users
**Deskripsi**: Tambah user baru

**Auth**: `role:admin`

**Request Body**:
```
name: string (required, max 100)
email: string (required, email, unique)
password: string (required, min 6, confirmed)
password_confirmation: string (required)
role: string (required, enum: admin|coach|school_pic)
school_id: integer (required jika role=school_pic, optional otherwise)
_token: string (CSRF token)
```

**Response - Success**:
```
Status: 302 Found (Redirect back)
Flash: 'success' => 'Akun berhasil dibuat!'
```

**Response - Error**:
```
Status: 302 Found (Back)
Flash: Errors + Input
```

---

#### PUT /admin/users/{user}
**Deskripsi**: Update data user

**Auth**: `role:admin`

**URL Parameters**:
```
user: integer (user ID)
```

**Request Body**:
```
name: string (required, max 100)
email: string (required, email, unique except current)
role: string (required, enum: admin|coach|school_pic)
school_id: integer (required jika role=school_pic)
_method: PUT
_token: string (CSRF token)
```

**Response**:
```
Status: 302 Found (Redirect back)
Flash: 'success' => 'Akun berhasil diperbarui!'
```

---

#### PATCH /admin/users/{user}/reset-password
**Deskripsi**: Reset password user

**Auth**: `role:admin`

**Request Body**:
```
password: string (required, min 6)
password_confirmation: string (required)
_token: string (CSRF token)
```

**Response**:
```
Status: 302 Found (Redirect back)
Flash: 'success' => 'Password akun [name] berhasil direset!'
```

---

#### DELETE /admin/users/{user}
**Deskripsi**: Hapus user

**Auth**: `role:admin`

**Response**:
```
Status: 302 Found (Redirect back)
Flash: 'success' => 'Akun berhasil dihapus.'
Note: Tidak bisa hapus akun sendiri
```

---

#### GET /admin/reports
**Deskripsi**: List laporan untuk review

**Auth**: `role:admin`

**Query Parameters**:
```
school_id: integer (optional)
status: string (optional, enum: draft|submitted|approved|rejected)
date_from: date (optional, YYYY-MM-DD)
date_to: date (optional, YYYY-MM-DD)
page: number (optional)
```

**Response**:
```html
<!-- Tabel laporan dengan filters -->
```

---

#### GET /admin/reports/{report}
**Deskripsi**: View detail laporan

**Auth**: `role:admin`

**URL Parameters**:
```
report: integer (report ID)
```

**Response**:
```html
<!-- Detail laporan dengan:
  - Report data (coach, class, school, etc)
  - Attendance list
  - Media (photos + videos)
  - Approval status
-->
```

---

#### PATCH /admin/reports/{report}/approve
**Deskripsi**: Approve laporan

**Auth**: `role:admin`

**Response**:
```
Status: 302 Found (Redirect back)
Flash: 'success' => 'Laporan #[id] berhasil disetujui.'
Updates:
  - status = 'approved'
  - approved_by = admin ID
  - approved_at = now()
```

---

#### PATCH /admin/reports/{report}/reject
**Deskripsi**: Reject laporan

**Auth**: `role:admin`

**Request Body**:
```
admin_notes: string (required, max 500)
_token: string
```

**Response**:
```
Status: 302 Found (Redirect back)
Flash: 'success' => 'Laporan #[id] ditolak dengan catatan.'
Updates:
  - status = 'rejected'
  - admin_notes = input
```

---

#### GET /admin/schools
**Deskripsi**: List sekolah

**Auth**: `role:admin`

**Response**:
```html
<!-- Tabel sekolah dengan CRUD actions -->
```

---

#### POST /admin/schools
**Deskripsi**: Tambah sekolah

**Request Body**:
```
name: string (required, max 150)
address: string (optional)
pic_name: string (optional, max 100)
_token: string
```

---

#### PUT /admin/schools/{school}
**Deskripsi**: Update sekolah

---

#### DELETE /admin/schools/{school}
**Deskripsi**: Hapus sekolah

---

#### GET /admin/classes
**Deskripsi**: List kelas

**Auth**: `role:admin`

---

#### POST /admin/classes
**Deskripsi**: Tambah kelas

**Request Body**:
```
school_id: integer (required)
name: string (required, max 100)
_token: string
```

---

#### DELETE /admin/classes/{class}
**Deskripsi**: Hapus kelas

---

#### GET /admin/coaches
**Deskripsi**: List semua coach dengan assignment kelas

**Auth**: `role:admin`

---

#### GET /admin/coaches/{coach}
**Deskripsi**: Detail coach + available classes untuk assign

**Auth**: `role:admin`

**URL Parameters**:
```
coach: integer (user ID, harus role=coach)
```

**Response**:
```html
<!-- Coach data:
  - Current assignments (dengan school)
  - Available classes (grouped by school)
-->
```

---

#### POST /admin/coaches/{coach}/assign
**Deskripsi**: Assign coach ke kelas

**Auth**: `role:admin`

**Request Body**:
```
class_id: integer (required, exists in classes)
_token: string
```

**Response**:
```
Status: 302 Found (Redirect back)
Flash: 'success' => 'Kelas berhasil di-assign ke coach!'
Note: Tidak boleh duplicate assignment
```

---

#### DELETE /admin/coaches/{coach}/assignments/{assignment}
**Deskripsi**: Hapus assignment coach dari kelas

**Auth**: `role:admin`

---

### C. Coach Endpoints

#### GET /coach/reports
**Deskripsi**: List laporan coach (milik coach yang login)

**Auth**: `role:coach`

**Query Parameters**:
```
page: number (optional)
```

**Response**:
```html
<!-- Tabel laporan milik coach dengan status:
  - draft
  - submitted
  - approved
  - rejected
  Actions: Edit (jika draft/rejected), View
-->
```

---

#### GET /coach/reports/create
**Deskripsi**: Tampilkan form buat laporan baru

**Auth**: `role:coach`

**Response**:
```html
<!-- Form dengan fields:
  - class_id (select, hanya kelas yang di-assign)
  - report_date (date picker)
  - lesson_material (textarea)
  - activity_summary (textarea)
  - notes (textarea, optional)
  - photos (file input, max 10)
  - videos (file input, max 3)
  - attendance (checkboxes per student)
-->
```

---

#### POST /coach/reports
**Deskripsi**: Submit laporan baru

**Auth**: `role:coach`

**Request Body** (multipart/form-data):
```
class_id: integer (required)
report_date: date (required)
lesson_material: string (required, max 1000)
activity_summary: string (required, max 2000)
notes: string (optional, max 1000)
photos[]: file (optional, image only, max 10)
videos[]: file (optional, video only, max 3)
attendance[student_id]: enum (present|absent|sick|permission)
_token: string
```

**Video Formats Accepted**:
```
video/mp4
video/mpeg
video/quicktime (mov)
video/x-msvideo (avi)
video/x-matroska (mkv)
video/webm
video/avi
```

**Response - Success**:
```
Status: 302 Found (Redirect)
Location: /coach/reports
Flash: 'success' => 'Laporan berhasil dikirim!'
Side effects:
  - CREATE reports row (status: submitted)
  - CREATE report_media rows (for each photo/video)
  - CREATE report_attendance rows (for each student)
  - Upload files to Cloudinary
```

**Response - Error**:
```
Status: 302 Found (Back)
Flash: Validation errors
```

---

#### GET /coach/reports/{report}/edit
**Deskripsi**: Tampilkan form edit laporan

**Auth**: `role:coach`

**Authorization Check**:
```
- Report must belong to coach (coach_id == auth()->id())
- Report status must be 'draft' or 'rejected'
```

**Response**:
```html
<!-- Form edit dengan pre-filled data + media management -->
```

---

#### PUT /coach/reports/{report}
**Deskripsi**: Update laporan

**Auth**: `role:coach`

**Authorization**:
```
- Coach must own report
- Status must be draft or rejected
```

**Request Body** (multipart/form-data):
```
report_date: date (required)
lesson_material: string (required, max 1000)
activity_summary: string (required, max 2000)
notes: string (optional)
photos[]: file (optional, new photos)
videos[]: file (optional, new videos)
delete_media[]: integer (optional, media IDs to delete)
attendance[student_id]: enum
_token: string
_method: PUT
```

**Response**:
```
Status: 302 Found (Redirect back)
Flash: 'success' => 'Laporan berhasil diperbarui!'
Updates:
  - Report data
  - Media (add new, delete selected)
  - Attendance
  - status reset to 'submitted'
  - admin_notes cleared
```

---

### D. Student Management Endpoints

#### GET /classes/{class}/students
**Deskripsi**: List siswa di kelas

**Auth**: Required

**URL Parameters**:
```
class: integer (class ID)
```

**Response**:
```html
<!-- List siswa dengan actions:
  - Manual add form
  - Import Excel form
  - Delete button per siswa
-->
```

---

#### POST /classes/{class}/students
**Deskripsi**: Tambah siswa manual

**Auth**: Required

**Request Body**:
```
name: string (required, max 100, unique di kelas)
_token: string
```

---

#### POST /classes/{class}/students/import
**Deskripsi**: Import siswa dari Excel/CSV

**Auth**: Required

**Request Body** (multipart/form-data):
```
file: file (required, mimes: xlsx|xls|csv, max 50MB)
_token: string

Column headers yang dicari:
  - 'nama_siswa' atau 'name' atau 'Nama Siswa'
```

**Response**:
```
Status: 302 Found (Back)
Flash: 'success' => '{imported} siswa berhasil diimport. {skipped} dilewati.'
```

---

#### DELETE /classes/{class}/students/{student}
**Deskripsi**: Hapus siswa

**Auth**: Required

---

#### GET /students/template
**Deskripsi**: Download template Excel untuk import

**Auth**: Required

**Response**:
```
Status: 200 OK
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content: File Excel dengan header 'nama_siswa'
```

---

### E. School PIC Endpoints

#### GET /pic/dashboard
**Deskripsi**: Dashboard PIC dengan laporan tersetujui

**Auth**: `role:school_pic`

**Query Parameters**:
```
class_id: integer (optional)
date_from: date (optional)
date_to: date (optional)
page: number (optional)
```

**Response**:
```html
<!-- List approved reports dari sekolah PIC
  - Total reports
  - Reports this month
  - Filters
  - Report list with detail view
-->
```

---

#### GET /pic/reports/{report}
**Deskripsi**: View detail report (hanya approved, sekolah sendiri)

**Auth**: `role:school_pic`

**Authorization Check**:
```
- Report.school_id == auth()->user()->school_id
- Report.status == 'approved'
```

---

### F. AJAX API Endpoints

#### GET /api/classes/{class}/students
**Deskripsi**: Get list siswa (JSON, untuk AJAX)

**Auth**: Required (session)

**Response**:
```json
[
  {
    "id": 1,
    "name": "Siswa A"
  },
  {
    "id": 2,
    "name": "Siswa B"
  }
]
```

**Status Code**: `200 OK`

---

## 4. Error Responses

### Validation Error (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Email sudah terdaftar"],
    "password": ["Password minimal 6 karakter"]
  }
}
```

### Unauthorized (401)
```
Status: 302 Found (Redirect)
Location: /login
```

### Forbidden (403)
```html
<!-- Error 403 page -->
Akses tidak diizinkan.
```

### Not Found (404)
```html
<!-- Error 404 page -->
Halaman tidak ditemukan.
```

### Server Error (500)
```html
<!-- Error 500 page -->
Terjadi kesalahan pada server.
```

---

## 5. Status Codes Reference

| Code | Meaning | Usage |
|------|---------|-------|
| 200 | OK | Berhasil get/view |
| 302 | Found (Redirect) | Redirect setelah create/update |
| 401 | Unauthorized | User belum login |
| 403 | Forbidden | User tidak punya akses |
| 404 | Not Found | Resource tidak ada |
| 422 | Unprocessable Entity | Validasi error |
| 500 | Server Error | Error di server |

---

## 6. Rate Limiting

**Current**: NOT IMPLEMENTED

**Recommended**:
- Login attempts: Max 5 attempts per 1 minute
- File upload: Max 10 uploads per 1 minute

---

## 7. CORS

**Status**: NOT APPLICABLE (Session-based, same-origin only)

---

## 8. Pagination

Default pagination:
- Users: 15 per page
- Laporan: 20 per page
- Kelas: 20 per page
- Siswa: 20 per page

Query string maintained saat navigate pages (preserving filters).

---

## 9. Testing API dengan cURL

### Login
```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "email=admin@example.com&password=password&_token=TOKEN" \
  -c cookies.txt
```

### Access Protected Route
```bash
curl -X GET http://localhost:8000/admin/dashboard \
  -b cookies.txt
```

### Create User
```bash
curl -X POST http://localhost:8000/admin/users \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -b cookies.txt \
  -d "name=John&email=john@example.com&password=123456&password_confirmation=123456&role=coach&_token=TOKEN"
```

