<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\TeachingSchedule;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use App\Services\CustomNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Composer notifikasi custom ke coach (audit UX 2026-09-11).
 *
 * Relation/SuperAdmin boleh menarget semua coach; PIC School hanya coach
 * yang mengajar di sekolah plot-nya (ditegakkan di CustomNotificationService,
 * bukan hanya di UI). Tipe: perubahan jadwal, reminder laporan, atau
 * warning operasional lain.
 */
class NotificationController extends Controller
{
    private const TYPES = [
        'schedule'  => 'Perubahan Jadwal',
        'reminder'  => 'Reminder Laporan',
        'operational' => 'Warning / Info Operasional',
    ];

    public function __construct(
        private AuthorizationService $authorization,
        private CustomNotificationService $notifications,
        private ActivityLogService $activityLog,
    ) {
    }

    public function create()
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'notifications.send'), 403, 'Permission tidak mencukupi.');

        $coaches = $this->notifications->targetableCoaches($user);
        $schedules = $this->targetableSchedules($user);
        $reports = $this->targetableReports($user);
        $types = self::TYPES;

        return view('admin.notifications.create', compact('coaches', 'schedules', 'reports', 'types'));
    }

    public function store(Request $request)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'notifications.send'), 403, 'Permission tidak mencukupi.');

        $validated = $request->validate([
            'type'        => ['required', 'in:schedule,reminder,operational'],
            'coach_ids'   => ['required', 'array', 'min:1'],
            'coach_ids.*' => ['integer', 'exists:users,id'],
            'title'       => ['required', 'string', 'max:150'],
            'message'     => ['required', 'string', 'max:1000'],
            'schedule_id' => ['nullable', 'integer', 'exists:teaching_schedules,id'],
            'report_id'   => ['nullable', 'integer', 'exists:reports,id'],
        ]);

        // Record terkait hanya boleh dari dalam scope pengirim.
        $actionUrl = null;
        if (!empty($validated['schedule_id'])) {
            $schedule = TeachingSchedule::findOrFail($validated['schedule_id']);
            $this->assertSchoolInScope($user, (int) $schedule->school_id);
            $actionUrl = route('admin.schedules.index');
        }
        if (!empty($validated['report_id'])) {
            $report = Report::findOrFail($validated['report_id']);
            $this->assertSchoolInScope($user, (int) $report->school_id);
            $actionUrl = route('admin.reports.show', $report);
        }

        $sent = $this->notifications->send(
            $user,
            $validated['coach_ids'],
            $validated['title'],
            $validated['message'],
            $validated['type'],
            $actionUrl,
        );

        $this->activityLog->log(
            $user,
            'notification.sent',
            'user',
            null,
            "Notifikasi '{$validated['title']}' dikirim ke {$sent} coach",
            ['type' => $validated['type'], 'count' => $sent, 'coach_ids' => $validated['coach_ids']],
            $request,
        );

        return redirect()
            ->route('admin.notifications.create')
            ->with('success', "Notifikasi berhasil dikirim ke {$sent} coach.");
    }

    private function targetableSchedules(User $user)
    {
        $scope = $user->isSuperAdmin() || $user->isRelationUser()
            ? null
            : $user->assignedSchoolIds();

        return TeachingSchedule::with(['school', 'schoolClass'])
            ->orderByDesc('session_date')
            ->limit(100)
            ->when($scope !== null, fn ($q) => $q->whereIn('school_id', $scope))
            ->get();
    }

    private function targetableReports(User $user)
    {
        $scope = $this->authorization->accessibleSchoolIds($user);

        return Report::with(['school', 'schoolClass'])
            ->orderByDesc('report_date')
            ->limit(100)
            ->when($scope !== null, fn ($q) => $q->whereIn('school_id', $scope))
            ->get();
    }

    private function assertSchoolInScope(User $user, int $schoolId): void
    {
        $scope = $this->authorization->accessibleSchoolIds($user);
        if ($scope !== null && !in_array($schoolId, $scope, true)) {
            abort(403, 'Record terkait berada di luar scope Anda.');
        }
    }

    private function actingUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
