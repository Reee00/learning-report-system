<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportService
{
    private function getMatrixData(Builder $query): array
    {
        $records = $query->with(['report.school', 'report.schoolClass', 'student'])->get();

        $dates = $records->pluck('report.report_date')
            ->filter()
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        $matrix = [];
        foreach ($records as $record) {
            $school = $record->report?->school?->name ?? 'Unknown School';
            $class = $record->report?->schoolClass?->name ?? 'Unknown Class';
            $student = $record->student?->name ?? 'Unknown Student';
            $date = optional($record->report?->report_date)->format('Y-m-d');
            
            if (!$date) continue;

            $status = match($record->status) {
                'present' => 'Hadir',
                'absent' => 'Absen',
                'sick' => 'Sakit',
                'permission' => 'Izin',
                default => '-'
            };

            if (!isset($matrix[$school])) {
                $matrix[$school] = [];
            }
            if (!isset($matrix[$school][$class])) {
                $matrix[$school][$class] = [];
            }
            if (!isset($matrix[$school][$class][$student])) {
                $matrix[$school][$class][$student] = [];
            }

            $matrix[$school][$class][$student][$date] = $status;
        }

        ksort($matrix);
        foreach ($matrix as $school => &$classes) {
            ksort($classes);
            foreach ($classes as $class => &$students) {
                ksort($students);
            }
        }

        return ['dates' => $dates, 'matrix' => $matrix];
    }

    public function downloadCsv(Builder $query, string $filename): StreamedResponse
    {
        $data = $this->getMatrixData($query);
        $dates = $data['dates'];
        $matrix = $data['matrix'];

        return response()->streamDownload(function () use ($dates, $matrix): void {
            $handle = fopen('php://output', 'w');

            $headers = ['School', 'Class', 'Student'];
            foreach ($dates as $date) {
                $headers[] = $date;
            }
            fputcsv($handle, $headers);

            foreach ($matrix as $school => $classes) {
                foreach ($classes as $class => $students) {
                    foreach ($students as $student => $attendanceDates) {
                        $row = [$school, $class, $student];
                        foreach ($dates as $date) {
                            $row[] = $attendanceDates[$date] ?? '-';
                        }
                        fputcsv($handle, $row);
                    }
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadPdf(Builder $query, string $filename)
    {
        $data = $this->getMatrixData($query);
        $dates = $data['dates'];
        $matrix = $data['matrix'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('attendance.export_pdf', compact('dates', 'matrix'))
            ->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
