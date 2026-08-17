<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportService
{
    public function download(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'tanggal',
                'sekolah',
                'kelas',
                'coach',
                'siswa',
                'status_absensi',
                'status_laporan',
            ]);

            $query->chunkById(500, function ($rows) use ($handle): void {
                foreach ($rows as $attendance) {
                    fputcsv($handle, [
                        optional($attendance->report?->report_date)->format('Y-m-d'),
                        $attendance->report?->school?->name,
                        $attendance->report?->schoolClass?->name,
                        $attendance->report?->coach?->name,
                        $attendance->student?->name,
                        $attendance->status,
                        $attendance->report?->status,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
