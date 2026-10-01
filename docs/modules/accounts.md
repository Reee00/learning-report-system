# Modul: Akun, Nomor WhatsApp & Ganti Password

Sinkron dengan kode: **2026-10-01**. Sumber: `app/Http/Controllers/AccountController.php`, `app/Http/Controllers/Admin/CoachController.php`, `app/Models/User.php`, `resources/views/account/settings.blade.php`, `resources/views/admin/master/coaches.blade.php`, `resources/views/admin/master/coach_show.blade.php`, `resources/views/partials/whatsapp-link.blade.php`, `resources/views/partials/password-toggle-scripts.blade.php`.

## Kenapa kolom baru

Audit awal: tabel `users` **tidak** punya kolom `phone`/`whatsapp` apa pun — satu-satunya kolom kontak adalah `email`. Tidak ada field lama yang bisa dipakai ulang, jadi dibuat satu kolom baru lewat migrasi `2026_10_01_000001_add_whatsapp_to_users_table`:

```php
$table->string('whatsapp', 32)->nullable();
```

Nullable tanpa default: data user yang sudah ada tidak berubah dan tetap valid tanpa nomor.

## Account Settings — semua role

| Method | URI | Name |
|---|---|---|
| GET | `account` | `account.edit` |
| PATCH | `account` | `account.update` |
| PATCH | `account/password` | `account.password.update` |

Terbuka untuk **seluruh role** (grup `auth` tanpa middleware permission). Alasannya: tidak ada wewenang administratif di sini — setiap user hanya menyunting **akunnya sendiri**.

- Target selalu `$request->user()`; **tidak ada** parameter id user di URL maupun body, sehingga menambahkan `?user_id=…` atau field `id` pada request tidak mengubah siapa yang disunting.
- Field yang bisa diubah: **Nama**, **Nomor WhatsApp**, dan **Password**.
- **Email tidak dapat diubah** dari halaman ini — ia identifier login. Ini juga menghindari tabrakan `unique:users,email`.
- Halaman hanya menampilkan nomor milik pemilik sesi; nomor user lain tidak pernah dirender di sini.

Dua form di halaman ini dikirim ke rute yang **berbeda** supaya kegagalan validasi yang satu tidak pernah membatalkan penyimpanan yang lain. Form password memakai error bag bernama `password` (`validateWithBag('password', …)`), sehingga pesannya tidak tercampur dengan pesan form profil di kartu sebelahnya.

## Ganti password sendiri

Semua role dapat mengganti password akunnya sendiri; tidak ada role yang dikecualikan dan tidak ada jalur administratif di sini (reset password user lain tetap milik `Admin\UserController`).

| Field | Aturan |
|---|---|
| `current_password` | wajib — diperiksa aturan `current_password` bawaan Laravel, yang memakai `Hash::check()` terhadap `getAuthPassword()` |
| `password` | wajib, `min:6`, `confirmed` — sama dengan kebijakan yang sudah berlaku di `Admin\UserController` |
| `password_confirmation` | wajib cocok dengan `password` |

Sesudah validasi, password disimpan dengan `Hash::make()` — hashing yang sama dengan pembuatan akun. Karena `current_password` memakai hashing yang sama dengan login, password lama langsung tidak berlaku begitu tersimpan.

**Activity log.** Peristiwanya dicatat sebagai `user.password_changed` (subject `user`, milik user itu sendiri) dengan metadata yang sengaja dibiarkan kosong: tidak ada satu pun nilai password — lama, baru, maupun konfirmasi — yang dikirim ke logger. `ActivityLogService::REDACTED_KEYS` sudah memuat `password`, `password_confirmation`, dan `current_password` sebagai lapisan kedua, tetapi form ini tetap tidak mengirimkannya sama sekali.

## Normalisasi & validasi nomor

`User::normalizeWhatsapp()` mengubah semua bentuk ketikan yang lazim menjadi bentuk kanonik `08xxxxxxxxxx`:

| Input | Tersimpan |
|---|---|
| `+62 812-3456-7890` | `081234567890` |
| `62812 3456 7890` | `081234567890` |
| `812.3456.7890` | `081234567890` |
| `081234567890` | `081234567890` |
| `''` / `'   '` / `null` | `NULL` |
| `abc-def` | ditolak validasi |

Normalisasi dijalankan **sebelum** validasi, sehingga aturan validasi (`User::whatsappValidationRules()`) hanya perlu memeriksa bentuk kanonik: `^08[0-9]{8,13}$`.

Input yang **ada isinya tetapi tidak mengandung satu digit pun** diperlakukan sebagai salah ketik, bukan sebagai penghapusan nomor — validator menolaknya dan nomor lama tidak berubah.

## Siapa yang boleh melihat nomor coach

Capability `coaches.contact` (lihat [permissions.md](../reference/permissions.md)):

| Role | Lihat nomor coach? |
|---|---|
| SuperAdmin | Ya (semua coach) |
| Relation | Ya (semua coach) |
| SPV Coach | Ya (semua coach) |
| PIC DK SCHOOL | Ya, **hanya coach di sekolahnya** |
| Coach | **Tidak** — hanya nomornya sendiri lewat Account Settings |
| Teacher School, Finance | Tidak — bahkan tidak bisa membuka daftar coach (403) |

### Scope PIC sekolah

Ditegakkan di `Admin\CoachController`, bukan di Blade:

- `viewerSchoolScope()` memakai `AuthorizationService::accessibleSchoolIds()`. `null` = global; array kosong = PIC belum diplot ke sekolah mana pun, dan artinya **tidak melihat coach mana pun** (bukan melihat semuanya).
- `scopeCoachQueryToSchools()` membatasi query; seorang coach masuk scope bila menyentuh sekolah itu lewat salah satu dari tiga jalur: `coach_classes`, `teaching_schedules` (coach utama), atau `teaching_schedule_coach` (coach pendamping). Coach yang mengajar lewat sesi tanpa penugasan permanen tetap terlihat oleh PIC sekolahnya.
- `ensureCoachInScope()` membuat URL detail coach sekolah lain berakhir **403**, bukan halaman terbuka.
- Pada halaman detail, daftar kelas yang di-assign disaring ke sekolah dalam scope agar penugasan di sekolah lain tidak terbaca.

### Penyembunyian di sisi server

Untuk role tanpa `coaches.contact`, `hideContactWhenNotPermitted()` **membuang nilai `whatsapp` dari model** sebelum view dirender — bukan hanya menyembunyikan kolom di Blade. Dengan begitu tampilan, JSON, maupun kode lain tidak dapat membacanya.

## Tampilan

- Label selalu **"Nomor WhatsApp"**.
- Nomor kosong ditampilkan sebagai **"Belum diatur"** (miring, abu-abu).
- Nomor terisi ditampilkan terbaca: `0812-3456-7890` (`User::whatsappDisplay()`).

## Nomor WhatsApp yang bisa diklik

Nomor yang tampil bagi role berwenang (dan nomor milik sendiri di Account Settings) dirender sebagai tautan keluar `https://wa.me/<62…>`, bukan teks mati.

`User::whatsappUrl()` menyusun tautannya dari nilai yang tersimpan, dan **menerima juga bentuk yang belum lewat `normalizeWhatsapp()`** (`62…`, `8…`, nomor hasil importer/seeder) supaya tautan tidak pernah salah bentuk hanya karena cara pengisiannya berbeda:

| Nilai | Tautan |
|---|---|
| `081234567890` | `https://wa.me/6281234567890` |
| `+62 812-3456-7890` | `https://wa.me/6281234567890` |
| `62812 3456 7890` | `https://wa.me/6281234567890` |
| `812.3456.7890` | `https://wa.me/6281234567890` |
| `null`, `''`, `'   '`, `abc-def`, `12345`, `0215551234` | **tidak ada tautan** |

Awalan `62`/`0` dibuang lebih dahulu, lalu bagian nasionalnya harus berawalan `8` — jadi nomor tetap/fixed line seperti `0215551234` mengembalikan `null`. Untuk nomor yang tidak dikenali, lebih baik tidak ada tautan daripada tautan ke nomor yang bukan milik siapa pun.

Markup tautannya (`target="_blank"` + `rel="noopener noreferrer"`) dan ketiga keadaannya — ada tautan, nomor tanpa tautan, dan "Belum diatur" — datang dari satu partial bersama, `resources/views/partials/whatsapp-link.blade.php`, yang dipakai oleh daftar coach, detail coach, dan kartu ringkasan Account Settings. Partial ini **hanya merender**; kewenangan tetap urusan pemanggil (`Admin\CoachController::hideContactWhenNotPermitted()` membuang nilai `whatsapp` di sisi server), sehingga role tanpa `coaches.contact` tidak pernah menerima nomor maupun tautannya.

**Bukan integrasi.** Ini murni tautan keluar biasa: tidak ada API WhatsApp, tidak ada token, tidak ada job pengiriman, dan tidak ada notifikasi WhatsApp yang dikirim. `tests/Feature/WhatsappLinkTest.php` memindai `app/`, `resources/views/`, `routes/`, dan `config/` untuk memastikan tidak ada `api.whatsapp.com`, `graph.facebook.com`, `wa.me/send`, atau `WHATSAPP_TOKEN` yang pernah muncul.

## Tombol tampil/sembunyikan password

Setiap field password (login dan tiga field di kartu Ganti Password) punya tombol mata di dalam `input-group`. Skripnya satu, `resources/views/partials/password-toggle-scripts.blade.php`, dipasang lewat listener `click` terdelegasi dengan penjaga idempotensi (`window.__passwordToggleBound`) sehingga boleh di-include berkali-kali tanpa menggandakan listener.

- Tombolnya `type="button"` — tidak pernah men-submit form, jadi tidak mengganggu pengiriman.
- Ikon berganti `bi-eye` ⇄ `bi-eye-off`; `aria-label`, `title`, dan `aria-pressed` ikut diperbarui supaya tetap terbaca teknologi bantu.
- **`input.value` tidak pernah disentuh** — itulah sebabnya nilai yang sudah diketik tidak hilang saat toggle. Skrip hanya mengganti `input.type`.
- `autocomplete="current-password"` / `username` dipertahankan agar password manager browser tetap bekerja.

Logika autentikasi tidak berubah sama sekali: rate limiting, regenerasi sesi, dan aturan throttle tetap seperti semula (dijaga `tests/Feature/LoginPasswordToggleTest.php`).

## Test terkait

`tests/Feature/AccountSettingsTest.php`, `tests/Feature/PasswordChangeTest.php`, `tests/Feature/WhatsappLinkTest.php`, `tests/Feature/LoginPasswordToggleTest.php`, `tests/Feature/LoginSecurityTest.php`.
