<?php

namespace App\Services;

use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * Import jadwal mengajar (modul manajemen jadwal, 2026-09-11).
 *
 * Dua format didukung dan dideteksi otomatis:
 *
 * A. Template ternormalisasi (CSV/XLSX) — kolom:
 *    tanggal, nama_sekolah, nama_kelas, email_coach, jam_mulai, jam_selesai, topik
 *    + kolom opsional: email_coach_tambahan (pisahkan dengan ";"), program,
 *    jumlah_murid, tools_dk, tools_rk, jalan_minggu_ini, keterangan.
 *
 * B. Workbook master perusahaan ("Sistem Academic - CENTER & DIGISchool"):
 *    sheet jadwal mingguan DIGISchool (SENIN-SABTU) dengan header
 *    LOKASI BERANGKAT | BERANGKAT | JAM SAMPAI | NAMA SEKOLAH | JAM KELAS |
 *    PROGRAM | KELAS | JUMLAH MURID | TOOLS DK | TOOLS RK | Coach | (spare) |
 *    Jalan Minggu Ini ? | KET. Layout mengikuti sel merge: nilai sekolah/
 *    program/keberangkatan menurun ke baris berikut; baris tanpa KELAS tetapi
 *    berisi coach = coach tambahan untuk sesi di atasnya. Sheet diidentifikasi
 *    dari nama sheet (mengandung SENIN..SABTU).
 *
 *    Workbook adalah POLA per HARI + SEKOLAH (refactor 2026-09-25): TIDAK ADA
 *    periode global. Tanggal mulai dibaca PER BLOK SEKOLAH dari kolom KET
 *    ("Mulai tanggal 3 Agustus 2026", "Start 10/08/2026", "3 Agustus 2026"),
 *    lalu dipakai sebagai anchor pertemuan ke-1 pola sekolah tersebut. Blok
 *    sekolah tanpa tanggal mulai yang valid DITANDAI dan dilewati — tidak ada
 *    tanggal yang dikarang. Jumlah pertemuan diambil dari parameter `meetings`
 *    (default 20) karena workbook tidak memuatnya per sekolah.
 *
 *    Import ulang tidak menduplikasi: pola dengan hari + sekolah + tanggal
 *    mulai sama dilewati, dan generate hanya menambah sesi yang kurang.
 *
 * Semua referensi (sekolah, kelas, program, coach) harus sudah ada di master
 * data; baris yang tidak dapat dipetakan dilewati dengan pesan error. Scope
 * sekolah pengimpor tetap dipaksa.
 */
class TeachingScheduleImportService
{
    private const DAY_KEYWORDS = ['SENIN' => 1, 'SELASA' => 2, 'RABU' => 3, 'KAMIS' => 4, 'JUMAT' => 5, 'SABTU' => 6];

    /**
     * Nama bulan Indonesia -> angka, untuk membaca KET "Mulai tanggal
     * 3 Agustus 2026" (Carbon tidak mengenali nama bulan Indonesia).
     */
    private const MONTHS_ID = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6,
        'jul' => 7, 'agu' => 8, 'ags' => 8, 'sep' => 9, 'okt' => 10,
        'nov' => 11, 'des' => 12,
    ];

    public function __construct(private AuthorizationService $authorization)
    {
    }

    /**
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function import(User $user, UploadedFile $file, ?int $meetings = null): array
    {
        $isXlsx = strtolower($file->getClientOriginalExtension()) === 'xlsx';

        if ($isXlsx && $this->hasCompanySheets($file->getPathname())) {
            // Tidak ada parameter tanggal mulai global: tanggal mulai dibaca
            // per blok sekolah dari kolom KET masing-masing.
            return $this->importCompanyWorkbook($user, $file->getPathname(), $meetings ?? 20);
        }

        return $this->importNormalized($user, $file);
    }

    // ------------------------------------------------------------------
    // Format A — template ternormalisasi
    // ------------------------------------------------------------------

    private function importNormalized(User $user, UploadedFile $file): array
    {
        $schoolIds = $this->scopedSchoolIds($user);

        $schoolsByName = School::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim((string) $name)) => $id]);
        $classesByKey = SchoolClass::get()->mapWithKeys(
            fn ($class) => [mb_strtolower($class->school_id . '|' . $class->name) => $class->id]
        );
        $coachesByEmail = User::where('role', User::ROLE_COACH)->pluck('id', 'email')
            ->mapWithKeys(fn ($id, $email) => [mb_strtolower(trim((string) $email)) => $id]);
        $programsByKey = $this->programsByKey();
        $coachesByNormalName = $this->coachesByNormalName();

        $imported = 0;
        $skipped = 0;
        $errors = [];

        (new FastExcel())->import($file->getPathname(), function ($row) use (
            $schoolIds, $schoolsByName, $classesByKey, $coachesByEmail, $programsByKey,
            $coachesByNormalName, &$imported, &$skipped, &$errors
        ) {
            $date = trim((string) ($row['tanggal'] ?? $row['date'] ?? ''));
            $schoolName = trim((string) ($row['nama_sekolah'] ?? $row['school'] ?? $row['sekolah'] ?? ''));
            $className = trim((string) ($row['nama_kelas'] ?? $row['kelas'] ?? $row['class'] ?? ''));
            $coachEmail = trim((string) ($row['email_coach'] ?? $row['coach'] ?? $row['email'] ?? ''));
            $startTime = trim((string) ($row['jam_mulai'] ?? ''));
            $endTime = trim((string) ($row['jam_selesai'] ?? ''));
            $topic = trim((string) ($row['topik'] ?? $row['materi'] ?? ''));

            if ($date === '' || $schoolName === '' || $className === '' || $coachEmail === '') {
                $skipped++;
                return null;
            }

            $schoolId = $schoolsByName[mb_strtolower($schoolName)] ?? null;
            if ($schoolId === null) {
                $skipped++;
                $errors[] = "Sekolah \"{$schoolName}\" tidak ditemukan.";
                return null;
            }

            if ($schoolIds !== null && !in_array((int) $schoolId, $schoolIds, true)) {
                $skipped++;
                $errors[] = "Sekolah \"{$schoolName}\" di luar scope Anda.";
                return null;
            }

            $classId = $classesByKey[mb_strtolower($schoolId . '|' . $className)] ?? null;
            if ($classId === null) {
                $skipped++;
                $errors[] = "Kelas \"{$className}\" pada \"{$schoolName}\" tidak ditemukan.";
                return null;
            }

            $coachId = $coachesByEmail[mb_strtolower($coachEmail)]
                ?? $coachesByNormalName[$this->normalizePersonName($coachEmail)] ?? null;
            if ($coachId === null) {
                $skipped++;
                $errors[] = "Coach \"{$coachEmail}\" tidak ditemukan.";
                return null;
            }

            try {
                $parsedDate = Carbon::parse($date)->toDateString();
            } catch (\Throwable) {
                $skipped++;
                $errors[] = "Tanggal \"{$date}\" tidak valid.";
                return null;
            }

            if (!$this->isValidTime($startTime) || !$this->isValidTime($endTime)) {
                $skipped++;
                $errors[] = "Format jam \"{$startTime}\"/\"{$endTime}\" tidak valid (gunakan HH:MM) pada \"{$schoolName}\" / \"{$className}\".";
                return null;
            }

            [$start, $end] = $this->normalizeTimePair($startTime, $endTime);

            $attributes = [
                'school_id' => $schoolId,
                'class_id' => $classId,
                'program_id' => $this->resolveProgram(trim((string) ($row['program'] ?? '')), $programsByKey),
                'coach_id' => $coachId,
                'session_date' => $parsedDate,
                'student_count' => $this->parseIntOrNull($row['jumlah_murid'] ?? null),
                'start_time' => $start,
                'end_time' => $end,
                'tools_dk' => $this->stringOrNull($row['tools_dk'] ?? null, 255),
                'tools_rk' => $this->stringOrNull($row['tools_rk'] ?? null, 255),
                'jalan_minggu_ini' => $this->parseBoolean($row['jalan_minggu_ini'] ?? null, true),
                'keterangan' => $this->stringOrNull($row['keterangan'] ?? null, 1000),
                'topic' => $topic !== '' ? $topic : null,
            ];

            if ($this->duplicateExists($attributes)) {
                $skipped++;
                return null;
            }

            $schedule = TeachingSchedule::create($attributes);

            foreach ($this->parseAdditionalCoachEmails((string) ($row['email_coach_tambahan'] ?? '')) as $email) {
                $extraId = $coachesByEmail[mb_strtolower($email)]
                    ?? $coachesByNormalName[$this->normalizePersonName($email)] ?? null;
                if ($extraId === null || (int) $extraId === (int) $coachId) {
                    continue;
                }
                $schedule->additionalCoaches()->syncWithoutDetaching([$extraId]);
            }

            $imported++;
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    // ------------------------------------------------------------------
    // Format B — workbook master perusahaan
    // ------------------------------------------------------------------

    /**
     * Apakah file memiliki minimal satu sheet jadwal mingguan perusahaan
     * (header berisi NAMA SEKOLAH + JAM KELAS + KELAS).
     */
    private function hasCompanySheets(string $path): bool
    {
        try {
            $reader = new XlsxReader();
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                if ($this->companyHeaderRowIndex($sheet) !== null) {
                    $reader->close();
                    return true;
                }
            }
            $reader->close();
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    private function importCompanyWorkbook(User $user, string $path, int $meetings): array
    {
        $schoolIds = $this->scopedSchoolIds($user);

        $schoolsByNormal = $this->schoolsByNormalName();
        $classesBySchoolAndNormal = SchoolClass::get()->groupBy('school_id')->mapWithKeys(
            fn ($group, $schoolId) => [$schoolId => $group->mapWithKeys(
                fn ($class) => [$this->normalizeClassName($class->name) => $class->id]
            )]
        );
        $programsByKey = $this->programsByKey();
        $coachesByNormalName = $this->coachesByNormalName();

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $reader = new XlsxReader();
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $day = $this->dayFromSheetName($sheet->getName());
                $headerRowIndex = $this->companyHeaderRowIndex($sheet);

                if ($day === null || $headerRowIndex === null) {
                    continue;
                }

                $result = $this->importCompanySheet(
                    $sheet,
                    $headerRowIndex,
                    $day,
                    $meetings,
                    $schoolIds,
                    $schoolsByNormal,
                    $classesBySchoolAndNormal,
                    $programsByKey,
                    $coachesByNormalName
                );

                $imported += $result['imported'];
                $skipped += $result['skipped'];
                $errors = array_merge($errors, $result['errors']);
            }
        } finally {
            $reader->close();
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Tiap BLOK SEKOLAH pada sheet menjadi satu pola (hari + sekolah +
     * tanggal mulai dari KET); tiap baris kelas menjadi satu baris pola.
     * Sesi pertemuan digenerate lewat ScheduleTemplateService.
     *
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    private function importCompanySheet(
        $sheet,
        int $headerRowIndex,
        int $day,
        int $meetings,
        ?array $schoolIds,
        Collection $schoolsByNormal,
        Collection $classesBySchoolAndNormal,
        Collection $programsByKey,
        Collection $coachesByNormalName
    ): array {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $columns = $this->companyColumnMap($sheet, $headerRowIndex);
        $coachColumns = $this->coachColumnIndexes($columns);

        // Nilai sel merge yang menurun ke baris berikutnya.
        $carry = [
            'departure_location' => null,
            'departure_time' => null,
            'arrival_time' => null,
            'school' => null,
            'program' => null,
            'jam_kelas' => null,
            // Tanggal mulai blok sekolah berjalan (dari KET). Null = blok ini
            // belum punya tanggal mulai yang valid -> ditandai, tidak dikarang.
            'school_start' => null,
        ];

        /** @var int|null $lastIndex indeks kandidat terakhir di $candidates (untuk baris lanjutan coach tambahan) */
        $lastIndex = null;
        $candidates = [];
        $rowIndex = -1;

        foreach ($sheet->getRowIterator() as $rowObj) {
            $rowIndex++;
            if ($rowIndex <= $headerRowIndex) {
                continue;
            }

            $cells = $this->rowCells($rowObj);

            $get = function (string $key) use ($cells, $columns) {
                $index = $columns[$key] ?? null;
                return $index === null ? '' : trim((string) ($cells[$index] ?? ''));
            };

            $kelas = $get('kelas');
            $coachValues = array_map(
                fn ($i) => trim((string) ($cells[$i] ?? '')),
                $coachColumns
            );
            $coachValues = array_values(array_filter(
                $coachValues,
                fn ($v) => $v !== '' && !in_array($v, ['-', '–', '—'], true)
            ));

            // Baris yang memuat NAMA SEKOLAH membuka BLOK SEKOLAH baru: tanggal
            // mulai pola diambil dari KET baris tersebut (kolom KET pada
            // workbook berisi "Mulai tanggal 3 Agustus 2026").
            $schoolCell = $get('school');
            if ($schoolCell !== '') {
                $carry['school'] = $schoolCell;
                $carry['school_start'] = null;

                $parsedStart = $this->parseKetStartDate($get('ket'));
                if ($parsedStart === null) {
                    $errors[] = "Sekolah \"{$schoolCell}\" (sheet \"{$sheet->getName()}\") tidak memiliki tanggal mulai pada kolom KET — blok ini dilewati. "
                        .'Tulis mis. "Mulai tanggal 3 Agustus 2026", atau isi pola lewat form jadwal.';
                } elseif ((int) $parsedStart->isoWeekday() !== $day) {
                    $errors[] = "Sekolah \"{$schoolCell}\" (sheet \"{$sheet->getName()}\"): tanggal mulai pada KET "
                        .$parsedStart->translatedFormat('d F Y').' bukan hari '.TeachingScheduleTemplate::DAY_LABELS[$day]
                        .' — blok ini dilewati.';
                } else {
                    $carry['school_start'] = $parsedStart->toDateString();
                }
            }

            // Carry-down untuk sel merge.
            foreach (['departure_location', 'departure_time', 'arrival_time', 'program', 'jam_kelas'] as $key) {
                $value = $get($key);
                if ($value !== '') {
                    $carry[$key] = $value;
                }
            }

            if ($kelas === '' && $coachValues === []) {
                continue; // baris kosong / break
            }

            if ($kelas === '') {
                // Baris lanjutan tanpa kelas: coach tambahan untuk kandidat terakhir.
                if ($lastIndex !== null && $coachValues !== []) {
                    foreach ($coachValues as $name) {
                        $coachId = $coachesByNormalName[$this->normalizePersonName($name)] ?? null;
                        if ($coachId === null) {
                            $errors[] = "Coach \"{$name}\" (baris lanjutan) tidak ditemukan — dilewati.";
                            continue;
                        }
                        $candidates[$lastIndex]['additional_coach_ids'] = array_values(array_unique(
                            array_merge($candidates[$lastIndex]['additional_coach_ids'], [(int) $coachId])
                        ));
                    }
                }
                continue;
            }

            // --- Kandidat baris pola baru ---
            $schoolName = $carry['school'] ?? '';
            $schoolId = $schoolsByNormal[$this->normalizeSchoolName($schoolName)] ?? null;
            if ($schoolId === null) {
                $skipped++;
                $errors[] = "Sekolah \"{$schoolName}\" (sheet \"{$sheet->getName()}\") tidak ditemukan di master data.";
                $lastIndex = null;
                continue;
            }

            if ($schoolIds !== null && !in_array((int) $schoolId, $schoolIds, true)) {
                $skipped++;
                $errors[] = "Sekolah \"{$schoolName}\" di luar scope Anda.";
                $lastIndex = null;
                continue;
            }

            // Blok tanpa tanggal mulai valid tidak pernah menyimpan apa pun.
            if ($carry['school_start'] === null) {
                $skipped++;
                $lastIndex = null;
                continue;
            }

            $classMap = $classesBySchoolAndNormal->get($schoolId) ?? collect();
            $classId = $classMap[$this->normalizeClassName($kelas)] ?? null;
            if ($classId === null) {
                $skipped++;
                $errors[] = "Kelas \"{$kelas}\" pada \"{$schoolName}\" tidak ditemukan di master data.";
                $lastIndex = null;
                continue;
            }

            if ($coachValues === []) {
                $skipped++;
                $errors[] = "Sesi \"{$kelas}\" pada \"{$schoolName}\" tidak memiliki coach — dilewati.";
                $lastIndex = null;
                continue;
            }

            $primaryCoachName = $coachValues[0];
            $primaryCoachId = $coachesByNormalName[$this->normalizePersonName($primaryCoachName)] ?? null;
            if ($primaryCoachId === null) {
                $skipped++;
                $errors[] = "Coach \"{$primaryCoachName}\" pada \"{$schoolName}\" / \"{$kelas}\" tidak ditemukan.";
                $lastIndex = null;
                continue;
            }

            [$start, $end] = $this->parseJamKelas($carry['jam_kelas'] ?? '');
            if (($carry['jam_kelas'] ?? '') !== '' && ($start === null || $end === null)) {
                $skipped++;
                $errors[] = "Format jam kelas \"" . $carry['jam_kelas'] . "\" pada \"{$schoolName}\" / \"{$kelas}\" tidak valid.";
                $lastIndex = null;
                continue;
            }

            [$departureTime, $arrivalTime] = $this->parseJamKelas(
                trim(($carry['departure_time'] ?? '') . ' - ' . ($carry['arrival_time'] ?? ''))
            );

            $candidate = [
                'day_of_week' => $day,
                'start_date' => $carry['school_start'],
                'meeting_count' => $meetings,
                'school_id' => (int) $schoolId,
                'class_id' => (int) $classId,
                'program_id' => $this->resolveProgram($carry['program'] ?? '', $programsByKey),
                'coach_id' => (int) $primaryCoachId,
                'additional_coach_ids' => [],
                'student_count' => $this->parseIntOrNull($get('jumlah_murid')),
                'start_time' => $start,
                'end_time' => $end,
                'tools_dk' => $this->stringOrNull($get('tools_dk'), 255),
                'tools_rk' => $this->stringOrNull($get('tools_rk'), 255),
                'jalan_minggu_ini' => $this->parseBoolean($get('jalan_minggu_ini'), true),
                // KET yang hanya berisi tanggal mulai tidak diulang sebagai catatan.
                'keterangan' => $this->stringOrNull($this->stripKetStartDate($get('ket')), 1000),
                'departure_location' => $this->stringOrNull($carry['departure_location'] ?? '', 100),
                'departure_time' => $departureTime,
                'arrival_time' => $arrivalTime,
                'pattern_name' => 'Excel '.TeachingScheduleTemplate::DAY_LABELS[$day].' — '.trim((string) $schoolName),
            ];

            foreach (array_slice($coachValues, 1) as $name) {
                $coachId = $coachesByNormalName[$this->normalizePersonName($name)] ?? null;
                if ($coachId === null || (int) $coachId === (int) $primaryCoachId) {
                    if ($coachId === null) {
                        $errors[] = "Coach \"{$name}\" (coach tambahan) tidak ditemukan — dilewati.";
                    }
                    continue;
                }
                $candidate['additional_coach_ids'][] = (int) $coachId;
            }

            // Kandidat disimpan dulu — pola dibuat setelah semua baris
            // (termasuk baris lanjutan coach tambahan) selesai diproses.
            $candidates[] = $candidate;
            $lastIndex = count($candidates) - 1;
        }

        if (empty($candidates)) {
            return ['imported' => 0, 'skipped' => $skipped, 'errors' => $errors];
        }

        // ---- Buat baris pola + generate sesi ----
        /** @var ScheduleTemplateService $generator */
        $generator = app(ScheduleTemplateService::class);

        foreach ($candidates as $candidate) {
            // Pola duplikat (hari + sekolah + kelas + jam sama pada tanggal
            // mulai yang sama) -> dilewati, sesi yang sudah ada tidak diduplikasi.
            if ($this->patternRowExists($candidate)) {
                $skipped++;
                continue;
            }

            $template = TeachingScheduleTemplate::create([
                'pattern_name'   => $candidate['pattern_name'],
                'start_date'     => $candidate['start_date'],
                'meeting_count'  => $candidate['meeting_count'],
                'day_of_week'    => $candidate['day_of_week'],
                'school_id'      => $candidate['school_id'],
                'class_id'       => $candidate['class_id'],
                'program_id'     => $candidate['program_id'],
                'coach_id'       => $candidate['coach_id'],
                'start_time'     => $candidate['start_time'],
                'end_time'       => $candidate['end_time'],
                'student_count'  => $candidate['student_count'],
                'tools_dk'       => $candidate['tools_dk'],
                'tools_rk'       => $candidate['tools_rk'],
                'jalan_minggu_ini' => $candidate['jalan_minggu_ini'],
                'keterangan'     => $candidate['keterangan'],
                'departure_location' => $candidate['departure_location'],
                'departure_time' => $candidate['departure_time'],
                'arrival_time'   => $candidate['arrival_time'],
            ]);

            if ($candidate['additional_coach_ids'] !== []) {
                $template->additionalCoaches()->sync($candidate['additional_coach_ids']);
            }

            // Import workbook = alat migrasi massal: pertahankan perilaku
            // lama yaitu TIDAK memveto bentrok coach antar baris (operator
            // memperbaikinya lewat manajemen jadwal).
            $result = $generator->generate($template, checkConflicts: false);
            $imported += $result['created'];

            if ($result['created'] === 0) {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Baris pola duplikat: kelas + jam sama pada hari dan TANGGAL MULAI yang
     * sama (import ulang tidak menduplikasi). Tanggal mulai berbeda = pola
     * berbeda, jadi tetap boleh diimpor.
     */
    private function patternRowExists(array $candidate): bool
    {
        if ($candidate['start_time'] === null || $candidate['end_time'] === null) {
            // Tanpa jam kelas, duplikasi dihitung lewat sesi per tanggal
            // (sesi tanpa jam tidak dianggap duplikat — perlakuan lama).
            return false;
        }

        // Pad "HH:MM" -> "HH:MM:SS" agar cocok dengan kolom time template.
        $pad = fn (string $time) => strlen($time) === 5 ? $time . ':00' : $time;

        return TeachingScheduleTemplate::query()
            ->where('school_id', $candidate['school_id'])
            ->where('class_id', $candidate['class_id'])
            ->where('day_of_week', (int) $candidate['day_of_week'])
            ->whereDate('start_date', $candidate['start_date'])
            ->where('start_time', $pad($candidate['start_time']))
            ->where('end_time', $pad($candidate['end_time']))
            ->exists();
    }

    /**
     * Baca tanggal mulai dari kolom KET. Bentuk yang didukung:
     * "Mulai tanggal 3 Agustus 2026", "Mulai 3 Agustus 2026",
     * "3 Agustus 2026", "3 Agustus", "10/08/2026", "2026-08-10".
     *
     * Tanpa tahun, tanggal dianggap jatuh pada tahun berjalan — pemanggil
     * tetap memvalidasi harinya cocok dengan sheet. Null bila tidak ada
     * tanggal yang bisa dibaca (blok ditandai, bukan dikarang tanggalnya).
     */
    private function parseKetStartDate(string $ket): ?Carbon
    {
        $ket = trim($ket);
        if ($ket === '') {
            return null;
        }

        $text = mb_strtolower($ket);

        // Format ISO / numerik: 2026-08-10 atau 10/08/2026 atau 10-8-2026.
        if (preg_match('/(?<!\d)(\d{4})-(\d{1,2})-(\d{1,2})(?!\d)/', $text, $m)) {
            return $this->safeDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/(?<!\d)(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})(?!\d)/', $text, $m)) {
            return $this->safeDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // Format Indonesia: "<tanggal> <nama bulan> [tahun]".
        foreach (self::MONTHS_ID as $name => $month) {
            if (preg_match('/(?<!\d)(\d{1,2})\s+'.$name.'\b\s*(\d{4})?/u', $text, $m) === 1) {
                $year = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : (int) today()->year;

                return $this->safeDate($year, $month, (int) $m[1]);
            }
        }

        return null;
    }

    private function safeDate(int $year, int $month, int $day): ?Carbon
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        try {
            return Carbon::create($year, $month, $day)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Buang bagian tanggal mulai dari KET agar tidak tersimpan dua kali
     * (tanggal mulai sudah menjadi `start_date` pola). Sisa teks lain pada KET
     * tetap dipertahankan sebagai keterangan.
     */
    private function stripKetStartDate(string $ket): string
    {
        $ket = trim($ket);
        if ($ket === '') {
            return '';
        }

        $cleaned = preg_replace(
            '/(mulai\s+(tanggal\s+)?|start\s+)?(\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{4}|\d{1,2}\s+('.implode('|', array_keys(self::MONTHS_ID)).')\b\s*\d{0,4})/iu',
            '',
            $ket
        );

        return trim((string) preg_replace('/\s+/u', ' ', (string) $cleaned), " \t\n\r\0\x0B,;-–—");
    }

    /**
     * Indeks baris header perusahaan (berisi NAMA SEKOLAH + JAM KELAS + KELAS)
     * dalam 10 baris pertama sheet, atau null bila bukan sheet jadwal.
     */
    private function companyHeaderRowIndex($sheet): ?int
    {
        $index = -1;
        foreach ($sheet->getRowIterator() as $rowObj) {
            $index++;
            if ($index >= 10) {
                break;
            }
            $values = array_map(
                fn ($v) => mb_strtoupper(trim((string) $v)),
                $this->rowCells($rowObj)
            );
            if (in_array('NAMA SEKOLAH', $values, true)
                && in_array('JAM KELAS', $values, true)
                && in_array('KELAS', $values, true)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Peta nama kolom ternormalisasi -> indeks sel, dari baris header.
     */
    private function companyColumnMap($sheet, int $headerRowIndex): array
    {
        $index = -1;
        foreach ($sheet->getRowIterator() as $rowObj) {
            $index++;
            if ($index < $headerRowIndex) {
                continue;
            }

            $map = [];
            foreach ($this->rowCells($rowObj) as $i => $value) {
                $name = mb_strtoupper(trim((string) $value));
                $name = preg_replace('/\s+/u', ' ', $name);
                $map[$i] = $name;
            }

            $columns = [];
            foreach ($map as $i => $name) {
                $columns[$this->headerKey($name)] = $i;
            }

            return $columns;
        }

        return [];
    }

    private function headerKey(string $name): string
    {
        return match ($name) {
            'LOKASI BERANGKAT', 'BERANGKAT (LOKASI)' => 'departure_location',
            'BERANGKAT', 'JAM BERANGKAT' => 'departure_time',
            'JAM SAMPAI', 'SAMPAI' => 'arrival_time',
            'NAMA SEKOLAH', 'SEKOLAH' => 'school',
            'JAM KELAS', 'JAM KELAS (SESI)' => 'jam_kelas',
            'PROGRAM' => 'program',
            'KELAS' => 'kelas',
            'JUMLAH MURID', 'JUMLAH SISWA' => 'jumlah_murid',
            'TOOLS DK', 'TOOL DK' => 'tools_dk',
            'TOOLS RK', 'TOOL RK' => 'tools_rk',
            'COACH', 'TEACHER', 'COACHES' => 'coach',
            'JALAN MINGGU INI', 'JALAN MINGGU INI ?', 'JALAN MINGGU INI?' => 'jalan_minggu_ini',
            'KET', 'KETERANGAN' => 'ket',
            default => 'col_' . $name,
        };
    }

    /**
     * Kolom coach = kolom "COACH" + kolom tak berlabel di antara COACH dan
     * kolom berlabel berikutnya (kolom spare coach pada workbook perusahaan).
     *
     * @return array<int, int>
     */
    private function coachColumnIndexes(array $columns): array
    {
        $coachIndex = $columns['coach'] ?? null;
        if ($coachIndex === null) {
            return [];
        }

        $nextLabeled = PHP_INT_MAX;
        foreach ($columns as $key => $i) {
            if ($i > $coachIndex && $key !== 'coach' && !str_starts_with($key, 'col_')) {
                $nextLabeled = min($nextLabeled, $i);
            }
        }

        $indexes = [$coachIndex];
        for ($i = $coachIndex + 1; $i < $nextLabeled; $i++) {
            $indexes[] = $i;
        }

        return $indexes;
    }

    private function dayFromSheetName(string $name): ?int
    {
        $upper = mb_strtoupper($name);
        foreach (self::DAY_KEYWORDS as $keyword => $isoDay) {
            if (str_contains($upper, $keyword)) {
                return $isoDay;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Pemetaan master data
    // ------------------------------------------------------------------

    private function schoolsByNormalName(): Collection
    {
        return School::get()->mapWithKeys(function ($school) {
            $normal = $this->normalizeSchoolName($school->name);
            return [$normal => $school->id];
        });
    }

    private function programsByKey(): Collection
    {
        return Program::get()->mapWithKeys(function ($program) {
            // Satu program bisa dipetakan lewat nama maupun kode.
            $result = [];
            foreach ([mb_strtolower(trim($program->name)), mb_strtolower(trim((string) $program->code))] as $key) {
                if ($key !== '') {
                    $result[$key] = $program->id;
                }
            }

            return $result;
        });
    }

    private function resolveProgram(string $value, Collection $programsByKey): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return $programsByKey[mb_strtolower($value)] ?? null;
    }

    private function coachesByNormalName(): Collection
    {
        return User::where('role', User::ROLE_COACH)->get()->mapWithKeys(
            fn ($coach) => [$this->normalizePersonName($coach->name) => $coach->id]
        );
    }

    // ------------------------------------------------------------------
    // Normalisasi nilai
    // ------------------------------------------------------------------

    private function normalizeSchoolName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/^sekolah\s+/u', '', $name);
        $name = preg_replace('/\s+/u', ' ', $name);

        return trim($name);
    }

    private function normalizeClassName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\bkelas\b/u', '', $name);
        $name = preg_replace('/\s*-\s*/u', '-', $name);
        $name = preg_replace('/\s+/u', ' ', $name);

        return trim($name);
    }

    private function normalizePersonName(string $name): string
    {
        $name = trim($name);
        // Buang gelar "Mr/Ms/Ibu/Pak" dan keterangan dalam kurung,
        // cth. "Mr Wildan (GS)" -> "wildan".
        $name = preg_replace('/\([^)]*\)/u', '', $name);
        $name = preg_replace('/^(mr|mrs|ms|bu|pak|bang|kak)\.?\s+/iu', '', $name);
        $name = mb_strtolower($name);
        $name = preg_replace('/\s+/u', ' ', $name);

        return trim($name);
    }

    /**
     * "14.30 - 15.30" / "12.15" / "13" -> ["14:30", "15:30"] (atau [null, null]).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function parseJamKelas(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [null, null];
        }

        preg_match_all('/(\d{1,2})\s*[.:]\s*(\d{1,2})|(?<!\d)(\d{1,2})(?!\d)/', $value, $matches, PREG_SET_ORDER);

        $times = [];
        foreach ($matches as $match) {
            if (isset($match[3]) && $match[3] !== '') {
                $hour = (int) $match[3];
                $minute = 0;
            } else {
                $hour = (int) $match[1];
                $minute = (int) str_pad($match[2], 2, '0', STR_PAD_LEFT);
            }
            if ($hour > 23 || $minute > 59) {
                return [null, null];
            }
            $times[] = sprintf('%02d:%02d', $hour, $minute);
            if (count($times) === 2) {
                break;
            }
        }

        if ($times === []) {
            return [null, null];
        }

        return [$times[0], $times[1] ?? null];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function normalizeTimePair(string $start, string $end): array
    {
        $normalize = function (string $value): ?string {
            if ($value === '') {
                return null;
            }
            $parts = explode(':', $value);

            return sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
        };

        return [$normalize($start), $normalize($end)];
    }

    private function isValidTime(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        return preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value) === 1;
    }

    private function parseBoolean(mixed $value, bool $default): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        $value = mb_strtolower(trim((string) $value));

        return in_array($value, ['1', 'x', 'v', 'y', 'ya', 'yes', 'true', '✓', '✔'], true);
    }

    private function parseIntOrNull(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        return is_numeric(trim((string) $value)) ? (int) trim((string) $value) : null;
    }

    private function stringOrNull(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * @return array<int, string>
     */
    private function parseAdditionalCoachEmails(string $value): array
    {
        return array_values(array_filter(array_map(
            fn ($email) => trim($email),
            preg_split('/[;,]/', $value) ?: []
        )));
    }

    private function duplicateExists(array $attributes): bool
    {
        $query = TeachingSchedule::query()
            ->where('school_id', $attributes['school_id'])
            ->where('class_id', $attributes['class_id'])
            ->whereDate('session_date', $attributes['session_date']);

        if ($attributes['start_time'] !== null && $attributes['end_time'] !== null) {
            // Pad ke "HH:MM:SS" agar whereTime konsisten (SQLite strftime).
            $query->whereTime('start_time', str_pad($attributes['start_time'], 8, ':00'))
                ->whereTime('end_time', str_pad($attributes['end_time'], 8, ':00'));
        } else {
            // Sesi tanpa jam tidak dianggap duplikat satu sama lain.
            return false;
        }

        return $query->exists();
    }

    private function rowCells($rowObj): array
    {
        $cells = [];
        foreach ($rowObj->getCells() as $cell) {
            $value = $cell->getValue();
            if ($value === null) {
                $cells[] = '';
            } elseif ($value instanceof \DateTimeInterface) {
                // Sel tanggal murni (KET "Mulai tanggal ...") harus mempertahankan
                // TAHUN — kalau diformat "d.m" seperti sel jam, tahunnya hilang dan
                // anchor pola tidak bisa dibaca. Sel berisi jam tetap "d.m" agar
                // parseJamKelas("14.30 - 15.30") bekerja seperti sebelumnya.
                $cells[] = $this->hasTimeComponent($value)
                    ? $value->format('H.i')
                    : $value->format('Y-m-d');
            } elseif (is_bool($value)) {
                $cells[] = $value ? '1' : '';
            } else {
                $cells[] = (string) $value;
            }
        }

        return $cells;
    }

    private function hasTimeComponent(\DateTimeInterface $value): bool
    {
        return $value->format('H:i:s') !== '00:00:00';
    }

    private function scopedSchoolIds(User $user): ?array
    {
        if ($user->isSuperAdmin() || $user->isRelationUser()) {
            return null;
        }

        return $user->assignedSchoolIds();
    }
}
