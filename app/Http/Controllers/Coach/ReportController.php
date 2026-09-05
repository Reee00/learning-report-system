<?php
namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\SchoolClass;
use App\Models\Student;
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
    ) {}

    /**
     * Resolve a class the acting coach is actually assigned to.
     * Assignment is the authorization boundary for every report write, so it is
     * checked in the backend and never inferred from the submitted form.
     */
    private function assignedClassOrFail(int $classId): SchoolClass
    {
        $class = SchoolClass::where('id', $classId)
            ->whereHas('coachAssignments', function ($q) {
                $q->where('coach_id', Auth::id());
            })
            ->first();

        abort_if($class === null, 403, 'Kelas ini bukan assignment Anda.');

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

            $label = $type === 'photo' ? 'Foto' : 'Video';

            throw ValidationException::withMessages([
                $type === 'photo' ? 'photos' : 'videos' => $label . ' "' . $file->getClientOriginalName()
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
        $classes = SchoolClass::whereHas('coachAssignments', function($q) {
            $q->where('coach_id', Auth::id());
        })->with('school')->get();

        return view('coach.reports.create', compact('classes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id'         => 'required|exists:classes,id',
            'report_date'      => 'required|date',
            'lesson_material'  => 'required|string|max:1000',
            'activity_summary' => 'required|string|max:2000',
            'notes'            => 'nullable|string|max:1000',
            'photos'           => 'nullable|array|max:10',
            'photos.*'         => 'file|image|max:10240',
            'videos'           => 'nullable|array|max:3',
            'videos.*'         => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/avi,video/mov|max:102400',
            'attendance'       => 'required|array',
            'attendance.*'     => 'in:present,absent,sick,permission',
        ]);

        $class = $this->assignedClassOrFail((int) $validated['class_id']);
        $this->assertAttendanceBelongsToClass($validated['attendance'], $class->id);

        // Satu laporan = baris report + media + absensi. Semuanya harus
        // mendarat bersama atau tidak sama sekali.
        DB::transaction(function () use ($request, $validated, $class) {
            $report = Report::create([
                'coach_id'         => Auth::id(),
                'school_id'        => $class->school_id,
                'class_id'         => $class->id,
                'report_date'      => $validated['report_date'],
                'lesson_material'  => $validated['lesson_material'],
                'activity_summary' => $validated['activity_summary'],
                'notes'            => $validated['notes'] ?? null,
                'status'           => 'submitted',
            ]);

            // Upload foto ke local storage (max 10)
            foreach ($request->file('photos') ?? [] as $photo) {
                $this->storeMedia($report, $photo, 'photo');
            }

            // Upload video ke local storage (max 3)
            foreach ($request->file('videos') ?? [] as $video) {
                $this->storeMedia($report, $video, 'video');
            }

            // Simpan absensi
            $this->syncAttendance($report, $validated['attendance']);
        });

        return redirect()->route('coach.reports.index')
            ->with('success', 'Laporan berhasil dikirim!');
    }

    public function edit(Report $report)
    {
        abort_if($report->coach_id !== Auth::id(), 403);
        abort_if(!in_array($report->status, ['draft', 'rejected']), 403, 'Laporan tidak bisa diedit.');

        $classes = SchoolClass::whereHas('coachAssignments', function($q) {
            $q->where('coach_id', Auth::id());
        })->with('school')->get();

        $students    = Student::where('class_id', $report->class_id)->get();
        $attendances = $report->attendances->keyBy('student_id');
        $report->load('photos', 'videos');

        return view('coach.reports.edit', compact('report', 'classes', 'students', 'attendances'));
    }

    public function update(Request $request, Report $report)
    {
        abort_if($report->coach_id !== Auth::id(), 403);
        abort_if(!in_array($report->status, ['draft', 'rejected']), 403);

        $validated = $request->validate([
            'report_date'      => 'required|date',
            'lesson_material'  => 'required|string|max:1000',
            'activity_summary' => 'required|string|max:2000',
            'notes'            => 'nullable|string|max:1000',
            'photos'           => 'nullable|array|max:10',
            'photos.*'         => 'file|image|max:10240',
            'videos'           => 'nullable|array|max:3',
            'videos.*'         => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/avi,video/mov|max:102400',
            'delete_media'     => 'nullable|array',
            'delete_media.*'   => 'exists:report_media,id',
            'attendance'       => 'required|array',
            'attendance.*'     => 'in:present,absent,sick,permission',
        ]);

        $this->assertAttendanceBelongsToClass($validated['attendance'], $report->class_id);

        $newPhotos = $request->file('photos') ?? [];
        $newVideos = $request->file('videos') ?? [];

        // Report, media dan absensi harus atomic. Penghapusan file dari disk
        // dikumpulkan dulu dan dieksekusi setelah commit agar DB-transaction
        // rollback tidak meninggalkan referensi ke file yang sudah terhapus.
        $mediaToDeleteFromDisk = DB::transaction(function () use ($validated, $report, $newPhotos, $newVideos) {
            $report->update([
                'report_date'      => $validated['report_date'],
                'lesson_material'  => $validated['lesson_material'],
                'activity_summary' => $validated['activity_summary'],
                'notes'            => $validated['notes'] ?? null,
                'status'           => 'submitted',
                'admin_notes'      => null,
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

            // Upload foto baru ke local storage
            foreach ($newPhotos as $photo) {
                $this->storeMedia($report, $photo, 'photo');
            }

            // Upload video baru ke local storage
            foreach ($newVideos as $video) {
                $this->storeMedia($report, $video, 'video');
            }

            // Update absensi
            $this->syncAttendance($report, $validated['attendance']);

            return $pendingDiskDeletes;
        });

        // Hapus file dari disk SETELAH transaksi berhasil commit
        foreach ($mediaToDeleteFromDisk as $media) {
            $this->mediaStorage->delete($media);
        }

        return redirect()->route('coach.reports.index')
            ->with('success', 'Laporan berhasil diperbarui dan dikirim ulang!');
    }
}