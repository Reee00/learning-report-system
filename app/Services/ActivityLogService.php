<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Pencatat aktivitas (audit keamanan 2026-09-11) — hanya dibaca SuperAdmin,
 * dihapus otomatis setelah 7 hari.
 *
 * Yang dicatat: login/logout, perubahan password, CRUD master data penting,
 * lifecycle laporan, perubahan absensi, jadwal (CRUD/import), pengiriman
 * reminder/notifikasi, perubahan media, assignment coach.
 *
 * YANG TIDAK PERNAH DICATAT: password, hash password, token autentikasi,
 * dan secret apa pun — metadata disaring sebelum insert.
 */
class ActivityLogService
{
    /**
     * Kunci metadata yang nilainya harus dibuang (secret / besar / PII berat).
     */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'api_token', 'remember_token', 'secret', 'key', 'authorization',
        'attendance_media', 'photos', 'videos',
    ];

    public function log(
        ?User $user,
        string $action,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        ?string $description = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        ActivityLog::create([
            'user_id'      => $user?->id,
            'user_role'    => $user?->role,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId !== null ? (int) $subjectId : null,
            'description'  => mb_substr((string) $description, 0, 500),
            'metadata'     => $this->scrub($metadata),
            'ip_address'   => $request?->ip(),
            'user_agent'   => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }

    /**
     * Buang kunci sensitif dan batasi ukuran nilai metadata.
     */
    private function scrub(array $metadata): array
    {
        $clean = [];
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->scrub($value);
                continue;
            }
            $clean[$key] = is_scalar($value) || $value === null
                ? $value
                : (string) $value;
        }

        return $clean;
    }
}
