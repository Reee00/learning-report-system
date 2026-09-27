<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\User;

class AuthorizationService
{
    /**
     * Central role-to-capability map for the current and planned roles.
     * SuperAdmin is handled as a wildcard so new capabilities remain global.
     */
    private const ROLE_PERMISSIONS = [
        'relation' => [
            'dashboard.view',
            'schools.view',
            'schools.create',
            'schools.update',
            'schools.delete',
            'students.view',
            'students.create',
            'students.delete',
            'program_classes.view',
            'program_classes.create',
            'program_classes.update',
            'program_classes.delete',
            'programs.view',
            'programs.create',
            'programs.update',
            'programs.delete',
            'attendance.view',
            'attendance.export',
            'reports.view_all',
            'reports.review',
            'reports.download',
            'reports.remind',
            'notifications.send',
            'schedules.view',
            'schedules.manage',
            // Relation bertindak operasional-global: boleh assign coach ke
            // kelas (re-use arsitektur CoachClass yang sama dengan SPV Coach).
            'coaches.view',
            'coaches.assign',
            'coaches.reassign',
        ],
        'spv_coach' => [
            'dashboard.view',
            'coaches.view',
            'coaches.create',
            'coaches.update',
            'coaches.assign',
            'coaches.reassign',
            'attendance.view',
            'attendance.export',
            'reports.view_all',
            'reports.download',
        ],
        'coach' => [
            'reports.view',
            'reports.create',
            'reports.update',
            'reports.download',
            'students.view',
            'students.create',
            'accident_notes.view',
            'schedules.view',
        ],
        'school_pic' => [
            'attendance.view',
            'attendance.export',
            'reports.view_all',
            'reports.download',
            'reports.remind',
            'notifications.send',
            'schedules.view',
            'schedules.manage',
            'students.view',
        ],
        'teacher_school' => [
            'attendance.view',
            'attendance.export',
            'reports.view_all',
            'reports.download',
        ],
        'finance' => [
            'attendance.view',
            'attendance.export_csv',
        ],
    ];

    public function allows(User $user, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array(
            $permission,
            self::ROLE_PERMISSIONS[$user->role] ?? [],
            true
        );
    }

    /**
     * SuperAdmin has global access. Relation is currently operational-global;
     * school-specific roles are restricted to their existing assignment.
     */
    public function canAccessClass(User $user, SchoolClass $class): bool
    {
        if ($user->isSuperAdmin() || $user->isRelationUser()) {
            return true;
        }

        if ($user->role === 'coach') {
            return $user->coachClasses()
                ->where('class_id', $class->id)
                ->exists();
        }

        if (in_array($user->role, ['school_pic', 'teacher_school'], true)) {
            return in_array($class->school_id, $user->assignedSchoolIds(), true);
        }

        return false;
    }

    /**
     * Boleh menulis laporan untuk kelas ini?
     *
     * Dua sumber kewenangan coach, dan hanya dua:
     *
     * 1. Penugasan PERMANEN lewat `coach_classes` — perilaku lama, berlaku
     *    untuk tanggal mana pun.
     * 2. Penugasan SEMENTARA lewat sesi mengajar (`teaching_schedules`),
     *    sebagai coach utama maupun coach tambahan, TANPA mengubah
     *    `coach_classes`. Coach yang ditarik dari sesi kehilangan akses ini,
     *    tetapi laporan yang sudah dibuat tetap tersimpan.
     *
     * Penugasan sementara sengaja TIDAK melihat is_active: menonaktifkan sesi
     * menghapus KEWAJIBAN laporan, bukan IZIN menulisnya — kalau tidak, coach
     * yang sesinya dinonaktifkan belakangan akan terkunci dari laporannya
     * sendiri.
     *
     * `$reportDate` (bila diisi) mengikat izin sementara ke sesi pada tanggal
     * itu saja, sehingga coach tambahan tidak mendapat akses ke seluruh kelas.
     */
    public function canReportOnClass(User $user, SchoolClass $class, ?string $reportDate = null): bool
    {
        if ($user->role !== User::ROLE_COACH) {
            return $this->canAccessClass($user, $class);
        }

        if ($user->coachClasses()->where('class_id', $class->id)->exists()) {
            return true;
        }

        $sessions = TeachingSchedule::query()
            ->where('class_id', $class->id)
            ->forCoach($user->id);

        if ($reportDate !== null) {
            $sessions->whereDate('session_date', $reportDate);
        }

        return $sessions->exists();
    }

    /**
     * Boleh melihat daftar siswa sebuah kelas?
     *
     * Dipakai endpoint roster yang dibutuhkan form laporan. Sengaja TERPISAH
     * dari canAccessClass supaya coach tambahan bisa mengisi absensi kelas yang
     * dia ajar tanpa ikut membuka wewenang pengelolaan siswa (tambah/hapus),
     * dan tanpa memberi akses ke sekolah secara global.
     */
    public function canViewClassRoster(User $user, SchoolClass $class): bool
    {
        return $this->canAccessClass($user, $class)
            || $this->canReportOnClass($user, $class);
    }

    /**
     * Null means operational-global scope; an empty array means no assigned
     * school scope. This distinction prevents PIC from falling back to all
     * schools when no plotting exists.
     *
     * Finance is deliberately global: the final business requirement is
     * all-school attendance visibility, so Finance must NOT be narrowed by
     * school plotting. Finance tetap dibatasi oleh status approval di
     * AttendanceScopeService, bukan oleh sekolah.
     */
    public function accessibleSchoolIds(User $user): ?array
    {
        if ($user->isSuperAdmin() || $user->isRelationUser()
            || $user->role === User::ROLE_SPV_COACH
            || $user->role === User::ROLE_COACH
            || $user->role === User::ROLE_FINANCE) {
            return null;
        }

        return $user->assignedSchoolIds();
    }

    public function canAccessSchool(User $user, int $schoolId): bool
    {
        $schoolIds = $this->accessibleSchoolIds($user);

        return $schoolIds === null || in_array($schoolId, $schoolIds, true);
    }
}
