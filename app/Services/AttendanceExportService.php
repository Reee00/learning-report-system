<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportService
{
    /**
     * Satu-satunya pemetaan status kehadiran → label untuk seluruh dokumen
     * export (CSV/PDF/Excel) — tidak boleh ada definisi ganda.
     */
    private const STATUS_LABELS = [
        'present' => 'Hadir',
        'absent' => 'Absen',
        'sick' => 'Sakit',
        'permission' => 'Izin',
    ];

    /**
     * QA M-003: matrix dibangun secara bertahap dengan chunk() sehingga
     * collection model tidak pernah dimuat sekaligus ke memory. Hasil
     * akhirnya identik dengan versi get() sebelumnya.
     */
    private function getMatrixData(Builder $query): array
    {
        $dates = collect();
        $matrix = [];

        $query->with(['report.school', 'report.schoolClass', 'student'])
            ->chunk(1000, function ($records) use (&$dates, &$matrix): void {
                foreach ($records as $record) {
                    $reportDate = $record->report?->report_date;
                    if (!$reportDate) {
                        continue;
                    }
                    $dates->push($reportDate->format('Y-m-d'));

                    $school = $record->report?->school?->name ?? 'Unknown School';
                    $class = $record->report?->schoolClass?->name ?? 'Unknown Class';
                    $student = $record->student?->name ?? 'Unknown Student';
                    $date = $reportDate->format('Y-m-d');

                    $status = self::STATUS_LABELS[$record->status] ?? '-';

                    $matrix[$school][$class][$student][$date] = $status;
                }
            });

        $dates = $dates->unique()->sort()->values();

        ksort($matrix);
        foreach ($matrix as $school => &$classes) {
            ksort($classes);
            foreach ($classes as $class => &$students) {
                ksort($students);
            }
        }

        return ['dates' => $dates, 'matrix' => $matrix];
    }

    /**
     * TOTAL HADIR per murid (keputusan UX 2026-09-13): jumlah tanggal dengan
     * status "Hadir" dihitung dari dataset yang sama yang dipakai dokumen —
     * key unik per [murid][tanggal] sehingga baris attendance ganda (jika
     * ada) otomatis tidak terhitung dua kali.
     *
     * @return array<string, array<string, array<string, int>>> [school][class][student] => total hadir
     */
    private function getTotals(array $matrix): array
    {
        $totals = [];
        foreach ($matrix as $school => $classes) {
            foreach ($classes as $class => $students) {
                foreach ($students as $student => $attendanceDates) {
                    $totals[$school][$class][$student] = $this->hadirCount($attendanceDates);
                }
            }
        }

        return $totals;
    }

    private function hadirCount(array $attendanceDates): int
    {
        return count(array_filter($attendanceDates, fn (string $status) => $status === 'Hadir'));
    }

    /**
     * Blok data per kelas untuk dokumen Excel & PDF (refactor export
     * 2026-09-13): satu kelas = satu sheet/halaman, berisi metadata
     * (sekolah, kelas, program, coach, periode), daftar tanggal kronologis,
     * matriks murid, TOTAL HADIR, ringkasan status, dan referensi bukti
     * absensi (nama file — media TIDAK pernah di-embed ke dokumen).
     *
     * Sumber data sama dengan CSV (AttendanceScopeService + filter yang
     * sama) — tidak ada perhitungan kehadiran terpisah.
     *
     * @return array<int, array{
     *   school: string, class: string, programs: string, coaches: string,
     *   period: string, dates: list<string>,
     *   students: array<string, array<string, string>>,
     *   totals: array<string, int>,
     *   summary: array<string, int>,
     *   media: list<array{date: string, name: string}>,
     * }>
     */
    private function buildClassBlocks(Builder $query): array
    {
        $blocks = [];

        $query->with(['report.school', 'report.schoolClass.programs', 'report.coach', 'report.attendanceMedia', 'student'])
            ->chunk(1000, function ($records) use (&$blocks): void {
                foreach ($records as $record) {
                    $report = $record->report;
                    $reportDate = $report?->report_date;
                    if (!$reportDate) {
                        continue;
                    }
                    $date = $reportDate->format('Y-m-d');

                    $schoolName = $report->school?->name ?? 'Unknown School';
                    $className = $report->schoolClass?->name ?? 'Unknown Class';
                    $key = $schoolName."\x00".$className;

                    if (!isset($blocks[$key])) {
                        $blocks[$key] = [
                            'school' => $schoolName,
                            'class' => $className,
                            'programs' => [],
                            'coaches' => [],
                            'dates' => [],
                            'students' => [],
                            'media' => [],
                        ];
                    }
                    $block = &$blocks[$key];

                    foreach ($report->schoolClass?->programs ?? [] as $program) {
                        $block['programs'][$program->name] = true;
                    }
                    if ($report->coach?->name) {
                        $block['coaches'][$report->coach->name] = true;
                    }
                    $block['dates'][$date] = true;

                    $student = $record->student?->name ?? 'Unknown Student';
                    $block['students'][$student][$date] = self::STATUS_LABELS[$record->status] ?? '-';

                    foreach ($report->attendanceMedia as $media) {
                        $block['media'][] = ['date' => $date, 'name' => $media->original_name ?? $media->path];
                    }
                }
            });

        ksort($blocks);

        foreach ($blocks as &$block) {
            $block['dates'] = array_keys($block['dates']);
            sort($block['dates']);

            ksort($block['students']);

            $block['programs'] = implode(', ', array_keys($block['programs']));
            $block['coaches'] = implode(', ', array_keys($block['coaches']));

            $period = $block['dates']
                ? \Carbon\Carbon::parse($block['dates'][0])->translatedFormat('d M Y')
                    .' - '.\Carbon\Carbon::parse($block['dates'][count($block['dates']) - 1])->translatedFormat('d M Y')
                : '-';

            $block['period'] = $period;

            $summary = array_fill_keys(array_values(self::STATUS_LABELS), 0);
            $block['totals'] = [];
            foreach ($block['students'] as $student => $attendanceDates) {
                $block['totals'][$student] = $this->hadirCount($attendanceDates);
                foreach ($attendanceDates as $status) {
                    if (array_key_exists($status, $summary)) {
                        $summary[$status]++;
                    }
                }
            }
            $block['summary'] = $summary;
        }

        return array_values($blocks);
    }

    public function downloadCsv(Builder $query, string $filename): StreamedResponse
    {
        $data = $this->getMatrixData($query);
        $dates = $data['dates'];
        $matrix = $data['matrix'];
        $totals = $this->getTotals($matrix);

        return response()->streamDownload(function () use ($dates, $matrix, $totals): void {
            $handle = fopen('php://output', 'w');

            $headers = ['School', 'Class', 'Student'];
            foreach ($dates as $date) {
                $headers[] = $date;
            }
            $headers[] = 'TOTAL HADIR';
            fputcsv($handle, $headers);

            foreach ($matrix as $school => $classes) {
                foreach ($classes as $class => $students) {
                    foreach ($students as $student => $attendanceDates) {
                        $row = [$school, $class, $student];
                        foreach ($dates as $date) {
                            $row[] = $attendanceDates[$date] ?? '-';
                        }
                        $row[] = (string) $totals[$school][$class][$student];
                        fputcsv($handle, $row);
                    }
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * XLSX — output administratif utama (refactor export 2026-09-13).
     * Satu sheet per kelas (murid kelas berbeda tidak pernah dicampur),
     * header metadata + tabel No | Nama Siswa | tanggal... | TOTAL HADIR.
     */
    public function downloadExcel(Builder $query, string $filename)
    {
        $blocks = $this->buildClassBlocks($query);

        $options = new Options();
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, null, 1));

        $writer = new Writer($options);
        $path = tempnam(sys_get_temp_dir(), 'attendance-xlsx').'.xlsx';
        $writer->openToFile($path);

        if ($blocks === []) {
            $this->writeEmptySheet($writer);
        } else {
            $usedSheetNames = [];
            foreach ($blocks as $index => $block) {
                $sheet = $index === 0
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();
                $this->writeClassSheet($writer, $options, $sheet, $block, $usedSheetNames);
            }
        }

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function writeEmptySheet(Writer $writer): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Laporan');

        $writer->addRow(Row::fromValues(['LAPORAN KEHADIRAN SISWA'], (new Style())->setFontBold()->setFontSize(14)));
        $writer->addRow(Row::fromValues(['Tidak ada data kehadiran untuk filter ini.']));
    }

    private function writeClassSheet(Writer $writer, Options $options, $sheet, array $block, array &$usedSheetNames): void
    {
        $dates = $block['dates'];
        $students = $block['students'];
        $lastColumn = count($dates) + 2; // indeks 0-based kolom TOTAL HADIR

        $sheet->setName($this->sheetName($block['class'], $block['school'], $usedSheetNames));
        $sheet->setSheetView(
            (new SheetView())->setFreezeRow(9)->setFreezeColumn('C')
        );

        // Lebar kolom terbaca: No & nama cukup lebar, kolom tanggal sempit
        // dan seragam, TOTAL HADIR sedikit lebih lebar. (indeks 1-based)
        $sheet->setColumnWidth(5, 1);
        $sheet->setColumnWidth(28, 2);
        if ($dates !== []) {
            $sheet->setColumnWidthForRange(11, 3, 2 + count($dates));
        }
        $sheet->setColumnWidth(13, 3 + count($dates));
        $sheet->setPrintTitleRows('8:8');

        // Baris judul & metadata digabung selebar tabel.
        $sheetIndex = $sheet->getIndex();
        foreach (range(1, 6) as $row) {
            $options->mergeCells(0, $row, $lastColumn, $row, $sheetIndex);
        }

        $titleStyle = (new Style())->setFontBold()->setFontSize(14);
        $metaStyle = (new Style())->setFontBold();

        $writer->addRow(Row::fromValues(['LAPORAN KEHADIRAN SISWA'], $titleStyle));
        $writer->addRow(Row::fromValues(['Sekolah : '.$block['school']], $metaStyle));
        $writer->addRow(Row::fromValues(['Kelas : '.$block['class']], $metaStyle));
        $writer->addRow(Row::fromValues(['Program : '.($block['programs'] ?: '-')], $metaStyle));
        $writer->addRow(Row::fromValues(['Coach : '.($block['coaches'] ?: '-')], $metaStyle));
        $writer->addRow(Row::fromValues(['Periode : '.$block['period']], $metaStyle));
        $writer->addRow(Row::fromValues(['']));

        $thinBorder = new Border(
            new BorderPart(Border::LEFT, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::RIGHT, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::TOP, Color::BLACK, Border::WIDTH_THIN),
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN),
        );

        $headerStyle = (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(30, 58, 95))
            ->setBorder($thinBorder)
            ->setCellAlignment(CellAlignment::CENTER);

        $cellStyle = (new Style())
            ->setBorder($thinBorder)
            ->setCellAlignment(CellAlignment::CENTER);

        $nameStyle = (new Style())->setBorder($thinBorder);

        $totalStyle = (new Style())
            ->setFontBold()
            ->setBackgroundColor(Color::rgb(232, 240, 254))
            ->setBorder($thinBorder)
            ->setCellAlignment(CellAlignment::CENTER);

        $header = array_merge(
            ['No', 'Nama Siswa'],
            array_map(
                static fn (string $date): string => \Carbon\Carbon::parse($date)->translatedFormat('d M'),
                $dates
            ),
            ['TOTAL HADIR']
        );
        $writer->addRow(Row::fromValues($header, $headerStyle));

        $number = 0;
        foreach ($students as $student => $attendanceDates) {
            ++$number;

            $row = Row::fromValues([$number, $student], $nameStyle);
            foreach ($dates as $date) {
                $row->addCell(\OpenSpout\Common\Entity\Cell::fromValue($attendanceDates[$date] ?? '-', $cellStyle));
            }
            $row->addCell(\OpenSpout\Common\Entity\Cell::fromValue($block['totals'][$student], $totalStyle));
            $writer->addRow($row);
        }
    }

    /**
     * Nama sheet XLSX: maks 31 karakter, tanpa karakter terlarang
     * ([]:*?/\), dan unik antar-sheet dalam satu workbook.
     */
    private function sheetName(string $class, string $school, array &$used): string
    {
        $name = trim(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $class) ?? '');
        if ($name === '' || mb_strtolower($name) === 'sheet') {
            $name = $class ?: $school;
        }
        $name = mb_substr($name, 0, 28);

        $base = $name;
        $suffix = 1;
        while (isset($used[$name])) {
            $name = mb_substr($base, 0, 28 - strlen((string) ++$suffix)).' '.$suffix;
        }
        $used[$name] = true;

        return $name;
    }

    public function downloadPdf(Builder $query, string $filename)
    {
        $blocks = $this->buildClassBlocks($query);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('attendance.export_pdf', ['blocks' => $blocks])
            ->setPaper('a4', 'landscape')
            ->setOption('enable_php', true); // nomor halaman di footer

        return $pdf->download($filename);
    }
}
