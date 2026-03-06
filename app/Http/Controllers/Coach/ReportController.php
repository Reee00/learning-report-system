<?php
namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\ReportMedia;
use App\Helpers\CloudinaryHelper;

class ReportController extends Controller
{
    public function index()
    {
        $reports = Report::with(['school', 'schoolClass'])
            ->where('coach_id', Auth::id())
            ->latest()
            ->paginate(15);

        return view('coach.reports.index', compact('reports'));
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
            'photos.*'         => 'file|image',
            'videos'           => 'nullable|array|max:3',
            'videos.*'         => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/avi,video/mov',
            'attendance'       => 'required|array',
            'attendance.*'     => 'in:present,absent,sick,permission',
        ]);

        $class = SchoolClass::findOrFail($validated['class_id']);

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

        // Upload foto ke Cloudinary (max 10)
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $result = CloudinaryHelper::upload($photo->getPathname(), 'lrs/photos');
                ReportMedia::create([
                    'report_id'     => $report->id,
                    'type'          => 'photo',
                    'path'          => $result['secure_url'],
                    'original_name' => $photo->getClientOriginalName(),
                ]);
            }
        }

        // Upload video ke Cloudinary (max 3)
        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $video) {
                $result = CloudinaryHelper::upload($video->getPathname(), 'lrs/videos');
                ReportMedia::create([
                    'report_id'     => $report->id,
                    'type'          => 'video',
                    'path'          => $result['secure_url'],
                    'original_name' => $video->getClientOriginalName(),
                ]);
            }
        }

        // Simpan absensi
        foreach ($validated['attendance'] as $studentId => $status) {
            ReportAttendance::create([
                'report_id'  => $report->id,
                'student_id' => $studentId,
                'status'     => $status,
            ]);
        }

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
            'photos.*'         => 'file|image',
            'videos'           => 'nullable|array|max:3',
            'videos.*'         => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/avi,video/mov',
            'delete_media'     => 'nullable|array',
            'delete_media.*'   => 'exists:report_media,id',
            'attendance'       => 'required|array',
            'attendance.*'     => 'in:present,absent,sick,permission',
        ]);

        $report->update([
            'report_date'      => $validated['report_date'],
            'lesson_material'  => $validated['lesson_material'],
            'activity_summary' => $validated['activity_summary'],
            'notes'            => $validated['notes'] ?? null,
            'status'           => 'submitted',
            'admin_notes'      => null,
        ]);

        // Hapus media yang dipilih
        if (!empty($validated['delete_media'])) {
            $mediaToDelete = ReportMedia::whereIn('id', $validated['delete_media'])
                ->where('report_id', $report->id)
                ->get();

            foreach ($mediaToDelete as $media) {
                // Hapus dari Cloudinary pakai public_id
                if (!empty($media->cloudinary_public_id)) {
                    CloudinaryHelper::delete($media->cloudinary_public_id);
                }
                $media->delete();
            }
        }

        // Cek total foto
        $currentPhotoCount = $report->photos()->count();
        $newPhotos = $request->file('photos') ?? [];
        if (($currentPhotoCount + count($newPhotos)) > 10) {
            return back()->with('error', 'Total foto tidak boleh lebih dari 10.');
        }

        // Cek total video
        $currentVideoCount = $report->videos()->count();
        $newVideos = $request->file('videos') ?? [];
        if (($currentVideoCount + count($newVideos)) > 3) {
            return back()->with('error', 'Total video tidak boleh lebih dari 3.');
        }

        // Upload foto baru ke Cloudinary
        foreach ($newPhotos as $photo) {
            $result = CloudinaryHelper::upload($photo->getPathname(), 'lrs/photos');
            ReportMedia::create([
                'report_id'     => $report->id,
                'type'          => 'photo',
                'path'          => $result['secure_url'],
                'original_name' => $photo->getClientOriginalName(),
            ]);
        }

        // Upload video baru ke Cloudinary
        foreach ($newVideos as $video) {
            $result = CloudinaryHelper::upload($video->getPathname(), 'lrs/videos');
            ReportMedia::create([
                'report_id'     => $report->id,
                'type'          => 'video',
                'path'          => $result['secure_url'],
                'original_name' => $video->getClientOriginalName(),
            ]);
        }

        // Update absensi
        $report->attendances()->delete();
        foreach ($validated['attendance'] as $studentId => $status) {
            ReportAttendance::create([
                'report_id'  => $report->id,
                'student_id' => $studentId,
                'status'     => $status,
            ]);
        }

        return redirect()->route('coach.reports.index')
            ->with('success', 'Laporan berhasil diperbarui dan dikirim ulang!');
    }
}