<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Route;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use Notifiable;

    // PWA Phase 2: subscription Web Push per perangkat (boleh banyak).
    // Hanya menambah jalur pengiriman; notifikasi database tetap sumber kebenaran.
    use HasPushSubscriptions;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_RELATION = 'relation';
    public const ROLE_SPV_COACH = 'spv_coach';
    public const ROLE_COACH = 'coach';
    public const ROLE_SCHOOL_PIC = 'school_pic';
    public const ROLE_TEACHER_SCHOOL = 'teacher_school';
    public const ROLE_FINANCE = 'finance';

    protected $fillable = ['name', 'email', 'password', 'role', 'school_id', 'whatsapp'];

    protected $hidden = ['password', 'remember_token'];

    // =====================================================================
    // Nomor WhatsApp (review meeting LRS 2026-10-01)
    //
    // Satu nomor per akun, dikelola sendiri oleh pemiliknya lewat Account
    // Settings. Disimpan ternormalisasi (`08xxxxxxxxxx`) supaya perbandingan
    // dan tampilan tidak bergantung pada cara user mengetik.
    // =====================================================================

    /**
     * Bentuk kanonik nomor WhatsApp Indonesia: `08` + 8–13 digit.
     *
     * Menerima `+62…`, `62…`, `8…`, spasi, tanda hubung, titik, dan tanda
     * kurung. Mengembalikan null HANYA untuk input kosong, supaya "tidak
     * punya nomor" tersimpan sebagai NULL, bukan string kosong.
     *
     * Dipakai BAIK oleh form Account Settings maupun sebelum validasi, jadi
     * nilai yang tersimpan tidak pernah bergantung pada format ketikan.
     */
    public static function normalizeWhatsapp(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $raw) ?? '';
        $digits = str_replace('+', '', $digits);

        if ($digits === '') {
            // Ada isi tetapi tanpa satu digit pun: itu SALAH KETIK, bukan
            // "dikosongkan". Dikembalikan apa adanya supaya validator yang
            // menolaknya — bukan diam-diam dianggap menghapus nomor.
            return $raw;
        }

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /**
     * Aturan validasi nomor WhatsApp — satu definisi, dipakai form akun.
     *
     * Selalu dijalankan SESUDAH normalizeWhatsapp(), jadi hanya bentuk
     * kanonik yang perlu diperiksa.
     *
     * @return array<int, string>
     */
    public static function whatsappValidationRules(): array
    {
        return ['nullable', 'string', 'regex:/^08[0-9]{8,13}$/'];
    }

    /**
     * Nomor siap dibaca: `0812-3456-7890`. Null bila user belum mengisi
     * nomornya — pemanggil yang memutuskan teks penggantinya ("Belum diatur").
     */
    public function whatsappDisplay(): ?string
    {
        $digits = (string) ($this->whatsapp ?? '');

        if ($digits === '') {
            return null;
        }

        return implode('-', array_filter([
            substr($digits, 0, 4),
            substr($digits, 4, 4),
            substr($digits, 8),
        ], fn (string $part): bool => $part !== ''));
    }

    public function hasWhatsapp(): bool
    {
        return ($this->whatsapp ?? '') !== '';
    }

    /**
     * Tautan WhatsApp klik-langsung untuk nomor ini (review meeting LRS
     * 2026-10-01), atau null bila nomor belum diatur.
     *
     * `wa.me` mensyaratkan bentuk internasional TANPA tanda `+`, spasi, atau
     * tanda hubung. Nomor kita tersimpan kanonik `08xxxxxxxxxx`, jadi awalan
     * `0` diganti `62` — `081234567890` menjadi `https://wa.me/6281234567890`.
     *
     * Sengaja MENERIMA juga bentuk lain (`62…`, `8…`, atau nomor yang belum
     * lewat `normalizeWhatsapp()` karena diisi importer/seeder) supaya tautan
     * tidak pernah salah bentuk hanya karena cara pengisiannya berbeda.
     *
     * Nilai yang tidak dapat dikenali sebagai nomor Indonesia mengembalikan
     * null — lebih baik tidak ada tautan daripada tautan ke nomor orang lain.
     *
     * Ini murni tautan keluar: TIDAK ada API WhatsApp dan TIDAK ada notifikasi
     * yang dikirim.
     */
    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) ($this->whatsapp ?? '')) ?? '';

        if ($digits === '') {
            return null;
        }

        // Buang awalan negara/trunk lebih dahulu, lalu periksa bentuk nasional.
        if (str_starts_with($digits, '62')) {
            $national = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            $national = $digits;
        }

        // Hanya nomor SELULER Indonesia (awalan `8`) yang punya WhatsApp.
        // Nomor lain — mis. `0215551234` — mengembalikan null: lebih baik tidak
        // ada tautan daripada tautan ke nomor yang bukan milik siapa pun.
        if (! str_starts_with($national, '8')) {
            return null;
        }

        return 'https://wa.me/62'.$national;
    }

    public static function roleKeys(): array
    {
        return [
            self::ROLE_SUPERADMIN,
            self::ROLE_RELATION,
            self::ROLE_SPV_COACH,
            self::ROLE_COACH,
            self::ROLE_SCHOOL_PIC,
            self::ROLE_TEACHER_SCHOOL,
            self::ROLE_FINANCE,
        ];
    }

    /**
     * Single source of truth for role display labels. Controllers, validation
     * and Blade all read from here so a new role cannot be missed in one place.
     *
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_SUPERADMIN => 'SuperAdmin',
            self::ROLE_RELATION => 'Relation',
            self::ROLE_SPV_COACH => 'SPV Coach',
            self::ROLE_COACH => 'Coach',
            self::ROLE_SCHOOL_PIC => 'School PIC',
            self::ROLE_TEACHER_SCHOOL => 'Teacher School',
            self::ROLE_FINANCE => 'Finance',
        ];
    }

    /**
     * Bootstrap badge colour per role, used by the account list.
     *
     * @return array<string, string>
     */
    public static function roleBadgeColors(): array
    {
        return [
            self::ROLE_SUPERADMIN => 'dark',
            self::ROLE_RELATION => 'danger',
            self::ROLE_SPV_COACH => 'info',
            self::ROLE_COACH => 'primary',
            self::ROLE_SCHOOL_PIC => 'success',
            self::ROLE_TEACHER_SCHOOL => 'secondary',
            self::ROLE_FINANCE => 'warning',
        ];
    }

    /**
     * Roles whose data access is limited to plotted schools, so at least one
     * school must be selected when the account is created or updated.
     *
     * Finance is NOT listed: scope-nya all-school (lihat
     * AuthorizationService::accessibleSchoolIds), jadi memplot sekolah untuk
     * Finance hanya akan menampilkan field yang tidak mengubah akses apa pun.
     *
     * @return array<int, string>
     */
    public static function schoolScopedRoles(): array
    {
        return [self::ROLE_SCHOOL_PIC, self::ROLE_TEACHER_SCHOOL];
    }

    /**
     * Route name halaman awal tiap role — satu definisi.
     *
     * Dipakai DUA tempat yang harus sepakat: redirect sesudah login, dan
     * fallback navigasi saat sesi/CSRF token kedaluwarsa (lihat
     * bootstrap/app.php). Sebelumnya pemetaan ini hanya hidup sebagai
     * `match` privat di LoginController, sehingga fallback di tempat lain
     * terpaksa menebak — dan tebakan yang salah berakhir redirect loop
     * login <-> '/'.
     *
     * Null berarti role tidak punya halaman awal.
     */
    public static function homeRouteName(?string $role): ?string
    {
        return match ($role) {
            self::ROLE_SUPERADMIN, self::ROLE_RELATION => 'admin.dashboard',
            self::ROLE_SPV_COACH => 'admin.coaches.index',
            self::ROLE_COACH => 'coach.reports.index',
            self::ROLE_SCHOOL_PIC => 'pic.dashboard',
            self::ROLE_TEACHER_SCHOOL, self::ROLE_FINANCE => 'attendance.index',
            default => null,
        };
    }

    /**
     * URL absolut halaman awal user ini, atau null bila role-nya belum
     * dipetakan. Pemanggil yang memutuskan apa arti null (biasanya: kirim
     * ke halaman login, bukan menebak sebuah route).
     */
    public function homeUrl(): ?string
    {
        $name = self::homeRouteName($this->role);

        return $name !== null && Route::has($name) ? route($name) : null;
    }

    public function roleLabel(): string
    {
        return self::roleLabels()[$this->role] ?? ucwords(str_replace('_', ' ', (string) $this->role));
    }

    public function roleBadgeColor(): string
    {
        return self::roleBadgeColors()[$this->role] ?? 'secondary';
    }

    public function isSchoolScoped(): bool
    {
        return in_array($this->role, self::schoolScopedRoles(), true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isRelationUser(): bool
    {
        return $this->role === self::ROLE_RELATION;
    }

    // Relasi legacy satu sekolah; scope baru menggunakan schools() pivot.
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function schools()
    {
        return $this->belongsToMany(School::class, 'school_user')
            ->withTimestamps();
    }

    /**
     * Returns the new multi-school scope and keeps legacy school_id compatible.
     */
    public function assignedSchoolIds(): array
    {
        $ids = $this->relationLoaded('schools')
            ? $this->schools->pluck('id')->all()
            : $this->schools()->pluck('schools.id')->all();

        if ($this->school_id !== null) {
            $ids[] = $this->school_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    // Relasi: coach bisa punya banyak penugasan kelas
    public function coachClasses()
    {
        return $this->hasMany(CoachClass::class, 'coach_id');
    }

    /**
     * Jadwal mengajar tempat user ini menjadi COACH UTAMA (teaching_schedules.
     * coach_id). Pasangan dari additionalSchedules().
     */
    public function teachingSchedules()
    {
        return $this->hasMany(TeachingSchedule::class, 'coach_id');
    }

    /**
     * Jadwal mengajar tempat user ini menjadi coach tambahan (pivot
     * teaching_schedule_coach). Dipakai untuk scope visibility coach.
     */
    public function additionalSchedules()
    {
        return $this->belongsToMany(
            TeachingSchedule::class,
            'teaching_schedule_coach',
            'coach_id',
            'schedule_id',
        )->withTimestamps();
    }

    // Laporan yang dibuat user ini sebagai coach; FK-nya RESTRICT sehingga
    // dipakai untuk memeriksa apakah akun masih boleh dihapus.
    public function reports()
    {
        return $this->hasMany(Report::class, 'coach_id');
    }
}
