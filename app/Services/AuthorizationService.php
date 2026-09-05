<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\User;

class AuthorizationService
{
    /**
     * Central role-to-capability map for the current and planned roles.
     * SuperAdmin is handled as a wildcard so new capabilities remain global.
     */
    private const ROLE_PERMISSIONS = [
        'relation' => [
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
        ],
        'school_pic' => [
            'attendance.view',
            'attendance.export',
            'reports.view_all',
            'reports.download',
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
     * Null means operational-global scope; an empty array means no assigned
     * school scope. This distinction prevents Finance/PIC from falling back
     * to all schools when no plotting exists.
     */
    public function accessibleSchoolIds(User $user): ?array
    {
        if ($user->isSuperAdmin() || $user->isRelationUser() || $user->role === User::ROLE_SPV_COACH
            || $user->role === User::ROLE_COACH) {
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
