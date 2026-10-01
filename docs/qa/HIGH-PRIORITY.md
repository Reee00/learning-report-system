# HIGH PRIORITY ISSUES — LRS QA Audit

**Status diperbarui: 2026-09-28.** Dokumen ini adalah subset dari [QA-AUDIT-REPORT.md](./QA-AUDIT-REPORT.md) — hanya berisi isu dengan severity HIGH.

> **Redaksi keamanan (2026-09-28).** Nilai kredensial Cloudinary yang sebelumnya tertulis apa adanya di dokumen ini sudah diganti placeholder. **CLOSED 2026-09-28** — kredensial lama sudah dihapus/diinvalidasi di sisi penyedia, sehingga salinan yang tersisa di riwayat git tidak lagi dapat dipakai. Lihat [12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md](../12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md).

---

## H-001: Cloudinary Credentials Committed to `.env`

**Status: CLOSED — 2026-09-28.** Integrasi Cloudinary dihapus penuh dari kode, dan kredensial lama sudah dihapus/diinvalidasi di sisi penyedia. Tidak ada tindakan lanjutan yang tersisa.

> **Judul dipertahankan apa adanya** sebagai catatan sejarah audit 2026-09-11. Berkas `.env` sebenarnya **tidak pernah** masuk repository — lihat klarifikasi di bawah.

**Issue:** Nilai API key dan secret Cloudinary pernah tertulis **apa adanya di dokumen ini dan di [QA-AUDIT-REPORT.md](./QA-AUDIT-REPORT.md)** — dua berkas yang **terlacak git**.

**Klarifikasi penting (hasil verifikasi 2026-09-28):** berkas `.env` **tidak pernah masuk repository** — `git ls-files .env` tidak menemukan apa pun, dan `.gitignore` memuat `.env`. Jadi jalur paparan yang sebenarnya adalah **dokumentasi QA**, bukan `.env`. Ini tidak mengurangi tingkat keparahan: nilainya tetap masuk riwayat git lewat berkas dokumen.

**Cause:** Nilai kredensial disalin ke dokumen audit saat pelaporan. Nilai aslinya **sudah di-redaksi** dari kedua dokumen ini; bentuk yang pernah ada:

```env
CLOUDINARY_CLOUD_NAME=<redacted>
CLOUDINARY_API_KEY=<redacted>
CLOUDINARY_API_SECRET=<redacted>
```

**Impact (historis, 2026-09-11):** Siapa pun dengan akses baca ke repository dapat menyalahgunakan akun Cloudinary (resource abuse, bandwidth drain), dan melanggar praktik pengelolaan kredensial. **Dampak ini sudah tertutup** — lihat tindakan lanjutan di bawah.

**Resolusi:**

1. Integrasi Cloudinary **dihapus sepenuhnya dari kode** (2026-09-11). Tidak ada lagi helper, perintah migrasi, maupun pemanggilan API. Media kini disimpan pada disk privat lokal — lihat [modules/media.md](../modules/media.md).
2. `.gitignore` sudah memuat `.env`.
3. Nilai asli di dokumen ini sudah diganti placeholder.
4. `.env.example` hanya memuat placeholder kosong.

**Tindakan lanjutan: tidak ada lagi (per 2026-09-28).**

- [x] **Selesai 2026-09-28** — kredensial Cloudinary lama **sudah dihapus/diinvalidasi** di dashboard penyedia. Salinan yang tersisa di riwayat git tidak lagi dapat dipakai.
- [x] Audit riwayat: `git log --all -p -- .env docs/qa/HIGH-PRIORITY.md` untuk memastikan tidak ada salinan lain.
- [x] **Selesai 2026-09-28** — blok `CLOUDINARY_*` di `.env.example` sudah **dihapus**, karena config tersebut tidak dipakai kode mana pun.

> **Catatan `.env` lokal.** Tiga baris `CLOUDINARY_*` di `.env` lokal adalah config mati (tidak ada kode yang membacanya) dan **tidak pernah terlacak git**. Karena kredensialnya sudah diinvalidasi di penyedia, baris-baris itu aman untuk dihapus kapan saja — penghapusannya tidak lagi mendesak dan diserahkan ke pemilik sistem.

---

## H-002: DashboardController Tidak Menerapkan School Scope

**Status: RESOLVED.**

**Issue:** Statistik dashboard (`total_reports`, `submitted_reports`, `approved_reports`, `rejected_reports`) dihitung dari **semua sekolah** tanpa memfilter berdasarkan school scope pengguna yang login.

**Resolusi terverifikasi** di `app/Http/Controllers/Admin/DashboardController.php`:

```php
// QA H-002: statistik dashboard wajib mengikuti school scope user.
// Null berarti scope operasional-global (SuperAdmin/Relation/SPV).
$schoolIds = $this->authorization->accessibleSchoolIds($user);
```

- `$reportQuery` dan `$schoolQuery` dibatasi `whereIn('school_id', $schoolIds)` / `whereIn('id', $schoolIds)` bila scope bukan `null`.
- `pendingReports` ikut dibatasi dengan pola `when()` yang sama.
- Pengguna yang bukan `User` ditolak 403 (`abort_unless`).

**Sisa yang perlu diperhatikan:** `total_coaches` **masih dihitung global** (`User::where('role','coach')->count()`). Saat ini dampaknya nihil karena hanya SuperAdmin, Relation, dan SPV Coach yang memiliki `dashboard.view`, dan ketiganya ber-scope global — tetapi bila kelak peran ber-scope sempit diberi akses dashboard, angka itu akan menyesatkan. Belum ada test yang menutup kasus ini.

**Verifikasi yang disarankan:** tambahkan test dashboard ber-scope agar perilaku ini tidak diam-diam rusak saat peran baru diberi `dashboard.view`.

---

## Ringkasan

| ID | Judul | Severity | Status |
|---|---|---|---|
| H-001 | Cloudinary credentials committed | HIGH | **CLOSED (2026-09-28)** — integrasi dihapus; kredensial lama sudah dihapus/diinvalidasi di penyedia |
| H-002 | Dashboard tanpa school scope | HIGH | **RESOLVED** — sisa: `total_coaches` masih global |
