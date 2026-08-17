<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttendanceExportService;
use App\Services\AttendanceScopeService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceScopeService $attendanceScope,
        private AttendanceExportService $attendanceExport,
        private AuthorizationService $authorization,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'attendance.view');

        $filters = $this->validatedFilters($request);
        $attendance = $this->attendanceScope->query($user, $filters)
            ->paginate(50)
            ->withQueryString();
        $schools = $this->attendanceScope->schoolsFor($user);
        $classes = $this->attendanceScope->classesFor($user);

        return view('attendance.index', compact('attendance', 'schools', 'classes'));
    }

    public function export(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $this->authorization->allows($user, 'attendance.export')
                || $this->authorization->allows($user, 'attendance.export_csv'),
            403,
            'Permission tidak mencukupi.'
        );

        $filters = $this->validatedFilters($request);
        $query = $this->attendanceScope->query($user, $filters);

        return $this->attendanceExport->download(
            $query,
            'attendance-'.now()->format('Ymd-His').'.csv'
        );
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'attendance_status' => ['nullable', 'in:present,absent,sick,permission'],
            'report_status' => ['nullable', 'in:draft,submitted,approved,rejected'],
        ]);
    }

    private function ensurePermission(User $user, string $permission): void
    {
        abort_unless(
            $this->authorization->allows($user, $permission),
            403,
            'Permission tidak mencukupi.'
        );
    }
}
