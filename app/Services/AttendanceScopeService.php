<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AttendanceScopeService
{
    public function __construct(private AuthorizationService $authorization)
    {
    }

    public function query(User $user, array $filters = []): Builder
    {
        $query = ReportAttendance::query()
            ->with(['student', 'report.school', 'report.schoolClass', 'report.coach'])
            ->whereHas('report', function (Builder $reports) use ($user): void {
                $this->scopeReports($reports, $user);
            });

        $query->when($filters['school_id'] ?? null, function (Builder $attendance, int|string $schoolId): void {
            $attendance->whereHas('report', fn (Builder $reports) => $reports->where('school_id', $schoolId));
        });

        $query->when($filters['class_id'] ?? null, function (Builder $attendance, int|string $classId): void {
            $attendance->whereHas('report', fn (Builder $reports) => $reports->where('class_id', $classId));
        });

        $query->when($filters['date_from'] ?? null, function (Builder $attendance, string $date): void {
            $attendance->whereHas('report', fn (Builder $reports) => $reports->whereDate('report_date', '>=', $date));
        });

        $query->when($filters['date_to'] ?? null, function (Builder $attendance, string $date): void {
            $attendance->whereHas('report', fn (Builder $reports) => $reports->whereDate('report_date', '<=', $date));
        });

        // Kolom SELALU di-qualify: pemanggil bisa menambahkan join ke tabel
        // reports (grouping per tanggal / summary), yang juga punya kolom
        // status — tanpa qualifier SQLite/MySQL melempar "ambiguous column".
        $query->when($filters['attendance_status'] ?? null, function (Builder $attendance, string $status): void {
            $attendance->where('report_attendances.status', $status);
        });

        $query->when($filters['report_status'] ?? null, function (Builder $attendance, string $status): void {
            $attendance->whereHas('report', fn (Builder $reports) => $reports->where('status', $status));
        });

        return $query->latest('report_attendances.id');
    }

    /**
     * Query laporan (satu laporan = satu sesi kehadiran per kelas+tanggal)
     * dalam scope autorisasi user — dipakai halaman drill-down attendance
     * (detail kelas → daftar tanggal, detail tanggal). Aturan scope sama
     * persis dengan query(): sekolah, status approval, penugasan coach.
     */
    public function scopedReports(User $user): Builder
    {
        return Report::query()->where(function (Builder $reports) use ($user): void {
            $this->scopeReports($reports, $user);
        });
    }

    public function schoolsFor(User $user)
    {
        $schoolIds = $this->authorization->accessibleSchoolIds($user);

        return School::query()
            ->when($schoolIds !== null, fn ($schools) => $schools->whereIn('id', $schoolIds))
            ->orderBy('name')
            ->get();
    }

    public function classesFor(User $user)
    {
        $schoolIds = $this->authorization->accessibleSchoolIds($user);

        return SchoolClass::with('school')
            ->when($schoolIds !== null, fn ($classes) => $classes->whereIn('school_id', $schoolIds))
            ->orderBy('name')
            ->get();
    }

    private function scopeReports(Builder $reports, User $user): void
    {
        $schoolIds = $this->authorization->accessibleSchoolIds($user);

        if ($schoolIds !== null) {
            $reports->whereIn('school_id', $schoolIds);
        }

        // Finance kini all-school (AuthorizationService), sehingga satu-satunya
        // pembatasnya adalah status approval — bukan daftar sekolah.
        if (in_array($user->role, [User::ROLE_SCHOOL_PIC, User::ROLE_TEACHER_SCHOOL, User::ROLE_FINANCE], true)) {
            $reports->where('status', 'approved');
        }

        if ($user->role === User::ROLE_COACH) {
            $reports->where('coach_id', $user->id);
        }

        if ($user->role === User::ROLE_SPV_COACH) {
            $reports->whereHas('schoolClass.coachAssignments');
        }
    }
}
