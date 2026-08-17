<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_RELATION = 'relation';
    public const ROLE_SPV_COACH = 'spv_coach';
    public const ROLE_COACH = 'coach';
    public const ROLE_SCHOOL_PIC = 'school_pic';
    public const ROLE_TEACHER_SCHOOL = 'teacher_school';
    public const ROLE_FINANCE = 'finance';

    protected $fillable = ['name', 'email', 'password', 'role', 'school_id'];

    protected $hidden = ['password', 'remember_token'];

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
     * @return array<int, string>
     */
    public static function schoolScopedRoles(): array
    {
        return [self::ROLE_SCHOOL_PIC, self::ROLE_TEACHER_SCHOOL, self::ROLE_FINANCE];
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

    // Laporan yang dibuat user ini sebagai coach; FK-nya RESTRICT sehingga
    // dipakai untuk memeriksa apakah akun masih boleh dihapus.
    public function reports()
    {
        return $this->hasMany(Report::class, 'coach_id');
    }
}
