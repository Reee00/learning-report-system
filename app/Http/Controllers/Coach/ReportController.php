<?php
namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\ReportMedia;

class ReportController extends Controller
{
    public function __construct(
        protected MediaStorageService $mediaStorage,
        private ActivityLogService $activityLog,
        private AuthorizationService $authorization,
    ) {}

    /**
     * Resolve a class the acting coach is actually assigned to.
     * Assignment is the authorization boundary for every report write, so it is
     * checked in the backend and never inferred from the submitted form.
     *
     * Dua bentuk penugasan diakui (lihat AuthorizationService::canReportOnClass):
     * `coach_classes` permanen, atau sesi mengajar pada tanggal laporan. Bentuk
     * kedua adalah penugasan SEMENTARA: coach tambahan yang dipasang pada satu
     * sesi tidak perlu — dan tidak boleh — otomatis masuk `coach_classes`.
     */
    private function assignedClassOrFail(int $classId, ?string $reportDate = null): SchoolClass
    {
        $class = SchoolClass::where('id', $classId)->first();

        abort_if($class === null, 403, 'Kelas ini bukan assignment Anda.');

        abort_unless(
            $this->authorization->canReportOnClass(Auth::user(), $class, $reportDate),
            403,
            'Kelas ini bukan assignment Anda.'
        );

        return $class;
    }

    /**
     * Attendance is posted as student_id => status, so the array keys are
     * untrusted input. Reject any student that does not belong to the class
     * being reported instead of blindly writing the row.
     */
    private function assertAttendanceBelongsToClass(array $attendance, int $classId): void
    {
        $submittedIds = array_map('intval', array_keys($attendance));
        $classStudentIds = Student::where('class_id', $classId)->pluck('id')->all();
        $foreignIds = array_values(array_diff($submittedIds, $classStudentIds));

        if ($foreignIds !== []) {
            throw ValidationException::withMessages([
                'attendance' => 'Absensi hanya boleh diisi untuk siswa pada kelas laporan ini.',
            ]);
        }
    }

    public function index()
    {
        $reports = Report::with(['school', 'schoolClass'])
            ->where('coach_id', Auth::id())
            ->latest()
            ->paginate(15);

        return view('coach.reports.index', compact('reports'));
    }

    /**
     * Simpan satu file ke local storage lalu catat di report_media.
     *
     * Jika penyimpanan gagal, lempar ValidationException agar transaksi
     * pemanggil rollback dan tidak meninggalkan laporan setengah tersimpan.
     */
    private function storeMedia(Report $report, UploadedFile $file, string $type): void
    {
        try {
            $this->mediaStorage->store($report, $file, $type);
        } catch (\Throwable $e) {
            Log::error('Media upload failed, report submission rolled back', [
                'report_id' => $report->id,
                'coach_id'  => Auth::id(),
                'type'      => $type,
                'file'      => $file->getClientOriginalName(),
                'error'     => $e->getMessage(),
            ]);

            $label = match ($type) {
                'photo'      => 'Foto',
                'attendance' => 'Foto absensi',
                default      => 'Video',
            };
            $key = match ($type) {
                'photo'      => 'photos',
                'attendance' => 'attendance_media',
                default      => 'videos',
            };

            throw ValidationException::withMessages([
                $key => $label . ' "' . $file->getClientOriginalName()
                    . '" gagal diunggah. Laporan TIDAK tersimpan — silakan coba kirim ulang.',
            ]);
        }
    }

    /**
     * Tulis ulang absensi laporan dari payload yang sudah tervalidasi.
     * Harus dijalankan di dalam transaksi: delete dan insert wajib mendarat
     * bersama, kalau tidak laporan bisa tertinggal tanpa absensi sama sekali.
     */
    private function syncAttendance(Report $report, array $attendance): void
    {
        $report->attendances()->delete();

        foreach ($attendance as $studentId => $status) {
            ReportAttendance::create([
                'report_id'  => $report->id,
                'student_id' => (int) $studentId,
                'status'     => $status,
            ]);
        }
    }

    public function create()
    {
        $classes = SchoolClass::reportableBy(Auth::id())->with('school')->get();

        return view('coach.reports.create', compact('classes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id'          => 'required|exists:classes,id',
            'report_date'       => 'required|date',
            'lesson_material'   => 'required|string|max:1000',
            'goals_materi'      => 'required|string|max:2000',
            'activity_report'   => 'required|string|max:2000',
            'notes'             => 'nullable|string|max:1000',
            'photos'            => 'nullable|array|max:10',
            'photos.*'          => 'file|image|max:10240',
            'videos'            => 'nullable|array|max:3',
            'videos.*'          => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/avi,video/mov|max:102400',
            'attendance_media'  => 'nullable|array|max:5',
            'attendance_media.*' => 'file|image|max:10240',
            'attendance'        => 'required|array',
            'attendance.*'      => 'in:present,absent,sick,permission',
        ]);

        $class = $this->assignedClassOrFail((int) $validated['class_id'], $validated['report_date']);
        $this->assertAttendanceBelongsToClass($validated['attendance'], $class->id);

        // Satu laporan = baris report + media + absensi. Semuanya harus
        // mendarat bersama atau tidak sama sekali.
        DB::transaction(function () use ($request, $validated, $class) {
            $report = Report::create([
                'coach_id'        => Auth::id(),
                'school_id'       => $class->school_id,
                'class_id'        => $class->id,
                'report_date'     => $validated['report_date'],
                'lesson_material' => $validated['lesson_material'],
                'goals_materi'    => $validated['goals_materi'],
                'activity_report' => $validated['activity_report'],
                'notes'           => $validated['notes'] ?? null,
                'status'          => 'submitted',
            ]);

            // Upload foto ke local storage (max 10)
            foreach ($request->file('photos') ?? [] as $photo) {
                $this->storeMedia($report, $photo, 'photo');
            }

            // Upload video ke local storage (max 3)
            foreach ($request->file('videos') ?? [] as $video) {
                $this->storeMedia($report, $video, 'video');
            }

            // Upload bukti kehadiran (max 5)
            foreach ($request->file('attendance_media') ?? [] as $media) {
                $this->storeMedia($report, $media, 'attendance');
            }

            // Simpan absensi
            $this->syncAttendance($report, $validated['attendance']);

            $this->activityLog->log(
                Auth::user(),
                'report.submitted',
                'report',
                $report->id,
                "Laporan dibuat & dikirim: {$class->school->name} / {$class->name}",
                ['school_id' => $report->school_id, 'class_id' => $report->class_id],
                $request,
            );
        });

        return redirect()->route('coach.reports.index')
            ->with('success', 'Laporan berhasil dikirim!');
    }

    public function edit(Report $report)
    {
        abort_if($report->coach_id !== Auth::id(), 403);
        abort_if(!in_array($report->status, ['draft', 'rejected']), 403, 'Laporan tidak bisa diedit.');

        $classes = SchoolClass::reportableBy(Auth::id())->with('school')->get();

        $students    = Student::where('class_id', $report->class_id)->get();
        $attendances = $report->attendances->keyBy('student_id');
        $report->load('photos', 'videos', 'attendanceMedia');

        return view('coach.reports.edit', compact('report', 'classes', 'students', 'attendances'));
    }

    public function update(Request $request, Report $report)
    {
        abort_if($report->coach_id !== Auth::id(), 403);
        abort_if(!in_array($report->status, ['draft', 'rejected']), 403);

        // QA M-006: assignment bisa berubah sejak laporan dibuat — pastikan
        // coach masih ditugaskan ke kelas laporan ini sebelum resubmit.
        // Untuk coach tambahan, penugasan sementaranya terikat TANGGAL sesi,
        // jadi tanggal laporan ikut diperiksa.
        $this->assignedClassOrFail((int) $report->class_id, $report->report_date->toDateString());

        $validated = $request->validate([
            'report_date'       => 'required|date',
            'lesson_material'   => 'required|string|max:1000',
            'goals_materi'      => 'required|string|max:2000',
            'activity_report'   => 'required|string|max:2000',
            'notes'             => 'nullable|string|max:1000',
            'photos'            => 'nullable|array|max:10',
            'photos.*'          => 'file|image|max:10240',
            'videos'            => 'nullable|array|max:3',
            'videos.*'          => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/avi,video/mov|max:102400',
            'attendance_media'  => 'nullable|array|max:5',
            'attendance_media.*' => 'file|image|max:10240',
            'delete_media'      => 'nullable|array',
            'delete_media.*'    => 'exists:report_media,id',
            'attendance'        => 'required|array',
            'attendance.*'      => 'in:present,absent,sick,permission',
        ]);

        $this->assertAttendanceBelongsToClass($validated['attendance'], $report->class_id);

        $newPhotos = $request->file('photos') ?? [];
        $newVideos = $request->file('videos') ?? [];
        $newAttendanceMedia = $request->file('attendance_media') ?? [];

        // Report, media dan absensi harus atomic. Penghapusan file dari disk
        // dikumpulkan dulu dan dieksekusi setelah commit agar DB-transaction
        // rollback tidak meninggalkan referensi ke file yang sudah terhapus.
        $mediaToDeleteFromDisk = DB::transaction(function () use ($validated, $report, $newPhotos, $newVideos, $newAttendanceMedia) {
            $report->update([
                'report_date'     => $validated['report_date'],
                'lesson_material' => $validated['lesson_material'],
                'goals_materi'    => $validated['goals_materi'],
                'activity_report' => $validated['activity_report'],
                'notes'           => $validated['notes'] ?? null,
                'status'          => 'submitted',
                'admin_notes'     => null,
            ]);

            // Hapus media yang dipilih — kumpulkan info untuk disk cleanup setelah commit
            $pendingDiskDeletes = [];

            if (!empty($validated['delete_media'])) {
                $mediaToDelete = ReportMedia::whereIn('id', $validated['delete_media'])
                    ->where('report_id', $report->id)
                    ->get();

                foreach ($mediaToDelete as $media) {
                    $pendingDiskDeletes[] = clone $media;
                    $media->delete();
                }
            }

            // Cek total foto (dihitung setelah penghapusan)
            if (($report->photos()->count() + count($newPhotos)) > 10) {
                throw ValidationException::withMessages([
                    'photos' => 'Total foto tidak boleh lebih dari 10.',
                ]);
            }

            // Cek total video (dihitung setelah penghapusan)
            if (($report->videos()->count() + count($newVideos)) > 3) {
                throw ValidationException::withMessages([
                    'videos' => 'Total video tidak boleh lebih dari 3.',
                ]);
            }

            // Cek total bukti kehadiran (dihitung setelah penghapusan)
            if (($report->attendanceMedia()->count() + count($newAttendanceMedia)) > 5) {
                throw ValidationException::withMessages([
                    'attendance_media' => 'Total foto absensi tidak boleh lebih dari 5.',
                ]);
            }

            // Upload foto baru ke local storage
            foreach ($newPhotos as $photo) {
                $this->storeMedia($report, $photo, 'photo');
            }

            // Upload video baru ke local storage
            foreach ($newVideos as $video) {
                $this->storeMedia($report, $video, 'video');
            }

            // Upload bukti kehadiran baru
            foreach ($newAttendanceMedia as $media) {
                $this->storeMedia($report, $media, 'attendance');
            }

            // Update absensi
            $this->syncAttendance($report, $validated['attendance']);

            return $pendingDiskDeletes;
        });

        // Hapus file dari disk SETELAH transaksi berhasil commit
        foreach ($mediaToDeleteFromDisk as $media) {
            $this->mediaStorage->delete($media);
        }

        $this->activityLog->log(
            Auth::user(),
            'report.resubmitted',
            'report',
            $report->id,
            "Laporan diperbarui & dikirim ulang ({$report->status})",
            request: $request,
        );

        return redirect()->route('coach.reports.index')
            ->with('success', 'Laporan berhasil diperbarui dan dikirim ulang!');
    }

    /**
     * Download (print-ready HTML) an approved Coach Report.
     *
     * Security: Coach can only download their OWN approved reports.
     * Status check prevents download of non-approved reports.
     */
    public function download(Report $report)
    {
        // Coach can only download their own reports
        abort_if($report->coach_id !== Auth::id(), 403, 'Anda tidak memiliki akses ke laporan ini.');
        abort_unless($report->status === 'approved', 403, 'Hanya laporan yang sudah disetujui dapat diunduh.');

        $report->load(['coach', 'school', 'schoolClass', 'attendances.student', 'media']);

        // Konteks tombol "Kembali" pada halaman unduhan: coach kembali ke
        // daftar laporan miliknya.
        $backTo = 'coach';

        // Build a safe filename: Coach-Report-{School}-{Coach}-{Date}
        $filename = 'Coach-Report-'
            . \Illuminate\Support\Str::slug($report->school->name) . '-'
            . \Illuminate\Support\Str::slug($report->coach->name) . '-'
            . $report->report_date->format('Y-m-d')
            . '.html';

        return response()
            ->view('admin.reports.download', compact('report', 'backTo'))
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
}