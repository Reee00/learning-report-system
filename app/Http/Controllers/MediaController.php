<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportMedia;
use App\Services\AuthorizationService;
use App\Services\MediaStorageService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves report media files with authorization enforcement.
 *
 * Media files are NOT stored in the public symlink. Every access goes through
 * this controller, which verifies the requesting user has permission to see
 * the associated report before streaming the file.
 */
class MediaController extends Controller
{
    public function __construct(
        private AuthorizationService $authorization,
        private MediaStorageService $mediaStorage,
    ) {}

    /**
     * Serve a media file if the user is authorized to view the report.
     *
     * Authorization rules match the existing report-view logic:
     * - SuperAdmin / Relation / SPV Coach: can view any report
     * - Coach: can only view own reports
     * - School PIC / Teacher / Finance: can view approved reports in their schools
     */
    public function serve(ReportMedia $media)
    {
        $report = $media->report;
        abort_if($report === null, 404);

        $this->authorizeMediaAccess($report);

        $absolutePath = $this->mediaStorage->absolutePath($media);
        abort_if($absolutePath === null, 404, 'File media tidak ditemukan.');

        $mimeType = $this->guessMimeType($media);
        $filename = $media->original_name ?? basename($media->path);

        // Unduh = route & otorisasi yang SAMA, hanya berbeda cara penyajian:
        // `?download=1` memaksa attachment. Dipakai tombol Download pada foto
        // dan video di halaman detail laporan — file privat tetap dilayani di
        // sini, tidak pernah dipindah ke public.
        $disposition = request()->boolean('download') ? 'attachment' : 'inline';

        // Stream the file to avoid loading large videos into memory
        return response()->file($absolutePath, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }

    /**
     * Enforce authorization for viewing media based on the report's access rules.
     */
    private function authorizeMediaAccess(Report $report): void
    {
        $user = Auth::user();
        abort_unless($user instanceof \App\Models\User, 403);

        // SuperAdmin/Relation/SPV Coach: can see all reports
        if ($user->isSuperAdmin() || $user->isRelationUser() || $user->role === \App\Models\User::ROLE_SPV_COACH) {
            return;
        }

        // Coach: can only view own reports
        if ($user->role === \App\Models\User::ROLE_COACH) {
            abort_unless((int) $report->coach_id === $user->id, 403, 'Anda tidak memiliki akses ke media ini.');
            return;
        }

        // Approval-gated roles: school access AND report must be approved.
        // Finance lolos canAccessSchool untuk semua sekolah (scope all-school),
        // jadi satu-satunya penahannya di sini adalah status approved.
        if (in_array($user->role, [\App\Models\User::ROLE_SCHOOL_PIC, \App\Models\User::ROLE_TEACHER_SCHOOL, \App\Models\User::ROLE_FINANCE], true)) {
            abort_unless(
                $this->authorization->canAccessSchool($user, (int) $report->school_id),
                403,
                'Anda tidak memiliki akses ke media sekolah ini.'
            );
            abort_unless($report->status === 'approved', 403, 'Laporan belum disetujui.');
            return;
        }

        // Any other role: denied
        abort(403, 'Anda tidak memiliki akses ke media ini.');
    }

    /**
     * Guess the MIME type based on file extension and media type.
     */
    private function guessMimeType(ReportMedia $media): string
    {
        $extension = strtolower(pathinfo($media->path, PATHINFO_EXTENSION));

        $mimeMap = [
            // Images
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            // Videos
            'mp4'  => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'mov'  => 'video/quicktime',
            'avi'  => 'video/x-msvideo',
            'mkv'  => 'video/x-matroska',
            'webm' => 'video/webm',
        ];

        return $mimeMap[$extension]
            ?? ($media->isVideo() ? 'video/mp4' : 'image/jpeg');
    }
}
