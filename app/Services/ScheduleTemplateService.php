<?php

namespace App\Services;

use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleTemplate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pola jadwal per HARI + SEKOLAH (refactor 2026-09-25).
 *
 * Model bisnis mengikuti workbook operasional DIGISchool: tiap sheet = satu
 * hari, dan DI DALAM satu hari ada banyak sekolah yang masing-masing punya
 * tanggal mulai sendiri (kolom KET: "Mulai tanggal 3 Agustus 2026"). Karena
 * itu TIDAK ADA periode global — setiap pola (hari + sekolah) menyimpan
 * `start_date` dan `meeting_count` sendiri.
 *
 * - Pola  -> kelompok baris teaching_schedule_templates dengan
 *            (day_of_week, school_id, start_date) yang sama.
 * - Sesi  -> teaching_schedules (template_id + meeting_number), sehingga
 *            reminder, visibility coach, multi-coach, dan filter existing
 *            bekerja tanpa perubahan aturan.
 *
 * Operator memasukkan POLA sekali; sesi pertemuan ke-1..N digenerate dari
 * `start_date` pola tersebut (berulang mingguan pada hari polanya). Libur atau
 * penyesuaian tanggal = hapus/pindah sesi individual, atau tandai
 * `jalan_minggu_ini = false` — pola tidak pernah berubah karenanya.
 */
class ScheduleTemplateService
{
    /**
     * Simpan pola dari builder/import lalu generate sesinya. Semua error
     * validasi dikumpulkan menjadi satu daftar (bukan berhenti di error
     * pertama) agar operator non-teknis bisa memperbaiki sekaligus.
     *
     * Payload:
     * - days => [ iso_day => [ blocks => [ [
     *       school_id, start_date, meeting_count,
     *       departure_location, departure_time, arrival_time,
     *       rows => [ [ class_id, program_id, coach_id, additional_coaches[],
     *         start_time, end_time, student_count, tools_dk, tools_rk,
     *         jalan_minggu_ini, keterangan, topic ] ]
     *   ] ] ] ]
     *
     * @return array{patterns: int, rows: int, sessions: int, warnings: array<int, string>}
     */
    public function build(User $user, array $payload): array
    {
        $errors = [];
        $schoolIds = $this->scopedSchoolIds($user);

        $entries = [];
        $patternsSeen = [];

        foreach (range(1, 7) as $day) {
            $blocks = $payload['days'][$day]['blocks'] ?? [];
            if (! is_array($blocks)) {
                continue;
            }

            $dayLabel = TeachingScheduleTemplate::DAY_LABELS_UPPER[$day] ?? "HARI {$day}";

            foreach ($blocks as $blockIndex => $block) {
                if (! is_array($block)) {
                    continue;
                }

                $blockLabel = "{$dayLabel} blok ".((int) $blockIndex + 1);
                $schoolId = (int) ($block['school_id'] ?? 0);
                $school = School::find($schoolId);
                $schoolLabel = $school?->name ?? "Sekolah #{$schoolId}";
                $prefix = "{$blockLabel} — {$schoolLabel}";

                if ($school === null) {
                    $errors[] = "{$prefix}: sekolah tidak ditemukan.";
                    continue;
                }

                if ($schoolIds !== null && ! in_array($schoolId, $schoolIds, true)) {
                    $errors[] = "{$prefix}: sekolah di luar scope Anda.";
                    continue;
                }

                // ---- Tanggal mulai milik SEKOLAH INI (bukan periode global) ----
                $startDate = $this->parseDate($block['start_date'] ?? null);
                if ($startDate === null) {
                    $errors[] = "{$prefix}: tanggal mulai wajib diisi untuk sekolah ini (kolom KET pada Excel, cth. \"Mulai tanggal 3 Agustus 2026\").";
                    continue;
                }

                if ((int) $startDate->isoWeekday() !== $day) {
                    $nearest = $this->nearestDates($startDate, $day);
                    $errors[] = "{$prefix}: tanggal mulai {$startDate->translatedFormat('d F Y')} bukan hari {$dayLabel}. "
                        .'Tanggal terdekat yang sesuai: '.implode(' atau ', $nearest).'.';
                    continue;
                }

                $meetingCount = (int) ($block['meeting_count'] ?? 20);
                if ($meetingCount < 1 || $meetingCount > 60) {
                    $errors[] = "{$prefix}: jumlah pertemuan harus antara 1 dan 60.";
                    continue;
                }

                // Dua blok dengan hari + sekolah + tanggal mulai sama = satu pola
                // yang ditulis dua kali.
                $patternKey = "{$day}|{$schoolId}|{$startDate->toDateString()}";
                if (isset($patternsSeen[$patternKey])) {
                    $errors[] = "{$prefix}: sekolah ini sudah punya blok dengan tanggal mulai yang sama pada hari {$dayLabel} di form ini. Gabungkan kelasnya dalam satu blok.";
                    continue;
                }
                $patternsSeen[$patternKey] = true;

                $schoolShared = [
                    'departure_location' => $this->nullableString($block['departure_location'] ?? null, 100),
                    'departure_time'     => $this->parseOptionalTime($block['departure_time'] ?? null),
                    'arrival_time'       => $this->parseOptionalTime($block['arrival_time'] ?? null),
                ];

                $rows = $block['rows'] ?? [];
                if (! is_array($rows) || $rows === []) {
                    $errors[] = "{$prefix}: minimal satu kelas/sesi harus diisi.";
                    continue;
                }

                // Semua tanggal pertemuan pola ini — dipakai untuk mendeteksi
                // bentrok coach yang benar-benar bertabrakan (dua pola berbeda
                // tanggal mulai belum tentu punya tanggal yang sama).
                $scheduleDates = [];
                for ($i = 0; $i < $meetingCount; $i++) {
                    $scheduleDates[] = $startDate->copy()->addWeeks($i)->toDateString();
                }

                foreach ($rows as $rowIndex => $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $rowLabel = "{$prefix} baris ".((int) $rowIndex + 1);

                    $classId = (int) ($row['class_id'] ?? 0);
                    $class = SchoolClass::find($classId);
                    if ($class === null) {
                        $errors[] = "{$rowLabel}: kelas tidak dipilih / tidak ditemukan.";
                        continue;
                    }
                    if ((int) $class->school_id !== $schoolId) {
                        $errors[] = "{$rowLabel} / {$class->name}: kelas tidak terdaftar pada sekolah ini.";
                        continue;
                    }

                    $label = "{$prefix} / {$class->name}";

                    $start = $this->parseRequiredTime($row['start_time'] ?? null);
                    $end = $this->parseRequiredTime($row['end_time'] ?? null);
                    if ($start === null || $end === null) {
                        $errors[] = "{$label}: jam kelas wajib diisi (format HH:MM).";
                        continue;
                    }
                    if ($end <= $start) {
                        $errors[] = "{$label}: jam selesai harus setelah jam mulai.";
                        continue;
                    }

                    $additionalIds = array_values(array_unique(array_map(
                        'intval',
                        array_filter((array) ($row['additional_coaches'] ?? []), 'is_numeric')
                    )));
                    $primaryId = (int) ($row['coach_id'] ?? 0);
                    if ($primaryId === 0) {
                        $errors[] = "{$label}: coach utama wajib dipilih.";
                        continue;
                    }

                    $coachIds = array_values(array_unique(array_merge([$primaryId], $additionalIds)));

                    // Coach utama wajib sudah ter-assign permanen ke kelas.
                    //
                    // Coach TAMBAHAN boleh belum: penugasan sementara di level
                    // jadwal memang untuk coach yang belum terdaftar di kelas
                    // ini (mis. coach pengganti dari sekolah lain). Aksesnya
                    // mengalir dari pivot teaching_schedule_coach pada sesi ini
                    // saja dan hilang begitu ia dicopot dari jadwal — tanpa
                    // menyentuh coach_classes.
                    $primaryAssigned = User::where('role', User::ROLE_COACH)
                        ->whereKey($primaryId)
                        ->whereHas('coachClasses', fn ($q) => $q->where('class_id', $class->id))
                        ->exists();

                    if (! $primaryAssigned) {
                        $name = User::whereKey($primaryId)->value('name') ?? 'Coach';
                        $errors[] = "{$label}: coach {$name} belum di-assign ke kelas ini. Atur assignment coach terlebih dahulu.";
                        continue;
                    }

                    // Coach tambahan tetap harus akun ber-role coach.
                    $notCoach = array_values(array_diff(
                        $additionalIds,
                        User::where('role', User::ROLE_COACH)->whereIn('id', $additionalIds)->pluck('id')
                            ->map(fn ($id) => (int) $id)->all()
                    ));
                    if ($notCoach !== []) {
                        $names = User::whereIn('id', $notCoach)->pluck('name')->implode(', ');
                        $errors[] = "{$label}: akun berikut bukan coach: {$names}.";
                        continue;
                    }

                    $additionalIds = array_values(array_diff($additionalIds, [$primaryId]));

                    // Kelas yang sama tidak boleh punya dua pola beririsan pada
                    // hari yang sama — sesinya akan bertabrakan saat digenerate.
                    if ($this->conflictingPatternExists($day, $schoolId, $classId, $start, $end)) {
                        $errors[] = "{$label}: kelas ini sudah punya pola pada hari {$dayLabel} dengan jam yang beririsan. Ubah jam atau hapus pola lama.";
                        continue;
                    }

                    $entries[] = [
                        'day_of_week'  => $day,
                        'day_label'    => $dayLabel,
                        'label'        => $label,
                        'school_id'    => $schoolId,
                        'class_id'     => $classId,
                        'program_id'   => ((int) ($row['program_id'] ?? 0)) ?: null,
                        'coach_id'     => $primaryId,
                        'additional_coach_ids' => $additionalIds,
                        'coach_ids'    => $coachIds,
                        'start_time'   => $start,
                        'end_time'     => $end,
                        'student_count' => ((int) ($row['student_count'] ?? 0)) ?: null,
                        'tools_dk'     => $this->nullableString($row['tools_dk'] ?? null, 255),
                        'tools_rk'     => $this->nullableString($row['tools_rk'] ?? null, 255),
                        'jalan_minggu_ini' => $this->parseBoolean($row['jalan_minggu_ini'] ?? true),
                        'topic'        => $this->nullableString($row['topic'] ?? null, 255),
                        'keterangan'   => $this->nullableString($row['keterangan'] ?? null, 1000),
                        'pattern_name' => $this->nullableString($row['pattern_name'] ?? null, 150)
                            ?? ('Excel '.$dayLabel.' — '.$schoolLabel),
                        'start_date'   => $startDate->toDateString(),
                        'meeting_count' => $meetingCount,
                        'schedule_dates' => $scheduleDates,
                        'school_shared' => $schoolShared,
                    ];
                }
            }
        }

        if ($entries === []) {
            $errors[] = 'Minimal satu kelas pada satu blok sekolah harus diisi.';
        }

        // Duplikat kelas + jam dalam payload yang sama.
        $seenRows = [];
        foreach ($entries as $entry) {
            $key = "{$entry['day_of_week']}|{$entry['class_id']}|{$entry['start_time']}|{$entry['end_time']}";
            if (isset($seenRows[$key])) {
                $errors[] = "{$entry['label']}: sesi dengan hari dan jam yang sama sudah diisi dua kali pada form ini.";
            }
            $seenRows[$key] = true;
        }

        $errors = array_merge($errors, $this->payloadCoachConflicts($entries));

        if ($errors !== []) {
            throw ValidationException::withMessages(['schedules' => array_values(array_unique($errors))]);
        }

        $rowsCreated = 0;
        $sessionsCreated = 0;
        $warnings = [];

        DB::transaction(function () use ($entries, &$rowsCreated, &$sessionsCreated, &$warnings): void {
            foreach ($entries as $entry) {
                $template = TeachingScheduleTemplate::create([
                    'pattern_name'   => $entry['pattern_name'],
                    'start_date'     => $entry['start_date'],
                    'meeting_count'  => $entry['meeting_count'],
                    'day_of_week'    => $entry['day_of_week'],
                    'school_id'      => $entry['school_id'],
                    'class_id'       => $entry['class_id'],
                    'program_id'     => $entry['program_id'],
                    'coach_id'       => $entry['coach_id'],
                    'start_time'     => $entry['start_time'],
                    'end_time'       => $entry['end_time'],
                    'student_count'  => $entry['student_count'],
                    'tools_dk'       => $entry['tools_dk'],
                    'tools_rk'       => $entry['tools_rk'],
                    'jalan_minggu_ini' => $entry['jalan_minggu_ini'],
                    'topic'          => $entry['topic'],
                    'keterangan'     => $entry['keterangan'],
                    'departure_location' => $entry['school_shared']['departure_location'],
                    'departure_time' => $entry['school_shared']['departure_time'],
                    'arrival_time'   => $entry['school_shared']['arrival_time'],
                ]);

                if ($entry['additional_coach_ids'] !== []) {
                    $template->additionalCoaches()->sync($entry['additional_coach_ids']);
                }

                $result = $this->generate($template, checkConflicts: true);
                $sessionsCreated += $result['created'];
                $warnings = array_merge($warnings, $result['warnings']);
                $rowsCreated++;
            }
        });

        return [
            'patterns' => count($patternsSeen),
            'rows'     => $rowsCreated,
            'sessions' => $sessionsCreated,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Generate sesi pertemuan untuk satu baris pola: `meeting_count` tanggal
     * berulang mingguan mulai dari `start_date` (hari pola itu sendiri).
     *
     * Tidak ada periode global dan tidak ada batas `period_end` — jumlah
     * pertemuan ditentukan `meeting_count`. Aman dipanggil ulang: sesi yang
     * sudah ada pada tanggal yang sama dilewati, dan jumlah sesi tidak pernah
     * melebihi `meeting_count` meski ada sesi yang dipindah karena libur.
     *
     * @return array{created: int, skipped: int, warnings: array<int, string>}
     */
    public function generate(TeachingScheduleTemplate $template, bool $checkConflicts = true): array
    {
        $capacity = max(1, (int) $template->meeting_count);

        $existing = $template->sessions()->orderBy('session_date')->get();
        $existingDates = $existing
            ->map(fn (TeachingSchedule $session) => $session->session_date->toDateString())
            ->flip();

        $remaining = $capacity - $existing->count();

        $created = 0;
        $skipped = 0;
        $warnings = [];

        if ($remaining <= 0) {
            return ['created' => 0, 'skipped' => 0, 'warnings' => []];
        }

        $additionalCoachIds = $template->additionalCoaches->pluck('id')->all();
        $coachIds = array_values(array_unique(array_merge([(int) $template->coach_id], array_map('intval', $additionalCoachIds))));

        $meetingNumber = $existing->count();

        foreach ($this->candidateDates($template) as $date) {
            if ($created >= $remaining) {
                break;
            }

            $dateString = $date->toDateString();

            if ($existingDates->has($dateString)) {
                continue;
            }

            if ($this->sessionExists($template, $dateString)) {
                // Sudah ada sesi kelas ini pada tanggal tersebut (mis. dibuat
                // manual / hasil import lama) — tautkan tanpa menduplikasi.
                $skipped++;
                $existingDates->put($dateString, true);
                $meetingNumber++;
                continue;
            }

            $startTime = $template->start_time?->format('H:i:s');
            $endTime = $template->end_time?->format('H:i:s');

            if ($checkConflicts && $this->coachConflictOnDate($coachIds, $dateString, $startTime, $endTime)) {
                $skipped++;
                $warnings[] = "{$template->school->name} — {$template->schoolClass->name} ({$template->dayLabel()}): "
                    ."pertemuan {$dateString} dilewati, coach sudah punya jadwal lain pada jam yang sama.";
                continue;
            }

            $session = TeachingSchedule::create([
                'template_id'      => $template->id,
                'meeting_number'   => ++$meetingNumber,
                'school_id'        => $template->school_id,
                'class_id'         => $template->class_id,
                'program_id'       => $template->program_id,
                'coach_id'         => $template->coach_id,
                'session_date'     => $dateString,
                'student_count'    => $template->student_count,
                'start_time'       => $template->start_time,
                'end_time'         => $template->end_time,
                'tools_dk'         => $template->tools_dk,
                'tools_rk'         => $template->tools_rk,
                'jalan_minggu_ini' => $template->jalan_minggu_ini,
                'topic'            => $template->topic,
                'keterangan'       => $template->keterangan,
                'departure_location' => $template->departure_location,
                'departure_time'   => $template->departure_time,
                'arrival_time'     => $template->arrival_time,
            ]);

            if ($additionalCoachIds !== []) {
                $session->additionalCoaches()->sync($additionalCoachIds);
            }

            $created++;
        }

        if ($created > 0) {
            $this->renumberSessions($template);
        }

        if ($template->sessions()->count() < $capacity) {
            $warnings[] = "{$template->school->name} — {$template->schoolClass->name} ({$template->dayLabel()}): "
                .$template->sessions()->count()." dari {$capacity} pertemuan tergenerate "
                .'(sisanya bentrok jadwal coach — perbaiki lalu generate ulang).';
        }

        return ['created' => $created, 'skipped' => $skipped, 'warnings' => $warnings];
    }

    /**
     * Tanggal pertemuan pola: `meeting_count` kemunculan mingguan mulai dari
     * `start_date` pola itu sendiri.
     *
     * @return array<int, Carbon>
     */
    public function candidateDates(TeachingScheduleTemplate $template): array
    {
        if ($template->start_date === null) {
            return [];
        }

        $count = max(1, (int) $template->meeting_count);
        $anchor = $template->start_date->copy()->startOfDay();

        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $dates[] = $anchor->copy()->addWeeks($i);
        }

        return $dates;
    }

    /**
     * Nomor pertemuan selalu urut 1..N mengikuti urutan tanggal, sehingga
     * penghapusan/pemindahan sesi (libur) tidak meninggalkan nomor bolong.
     */
    private function renumberSessions(TeachingScheduleTemplate $template): void
    {
        $sessions = $template->sessions()
            ->orderBy('session_date')
            ->orderBy('id')
            ->get();

        foreach ($sessions as $index => $session) {
            $number = $index + 1;
            if ((int) $session->meeting_number !== $number) {
                $session->forceFill(['meeting_number' => $number])->saveQuietly();
            }
        }
    }

    // ------------------------------------------------------------------
    // Daftar & aksi per pola
    // ------------------------------------------------------------------

    /**
     * Pola (hari + sekolah + tanggal mulai) dalam scope user, terpaginasi per
     * POLA (bukan per baris kelas) — dipakai halaman jadwal utama.
     *
     * @param  array{school_id?: ?int, coach_id?: ?int, class_id?: ?int}  $filters
     * @return array{patterns: Collection<int, array<string, mixed>>, paginator: LengthAwarePaginator}
     */
    public function paginatedPatterns(User $user, ?int $day = null, array $filters = [], int $perPage = 15): array
    {
        $keyQuery = $this->scopePatternQuery(DB::table('teaching_schedule_templates'), $user)
            ->select('day_of_week', 'school_id', 'start_date')
            ->when($day !== null, fn ($q) => $q->where('day_of_week', $day))
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('school_id', (int) $filters['school_id']))
            ->when(! empty($filters['class_id']), fn ($q) => $q->where('class_id', (int) $filters['class_id']))
            ->when(! empty($filters['coach_id']), function ($q) use ($filters) {
                $coachId = (int) $filters['coach_id'];
                $q->where(function ($inner) use ($coachId) {
                    $inner->where('coach_id', $coachId)
                        ->orWhereIn('id', function ($sub) use ($coachId) {
                            $sub->select('template_id')
                                ->from('teaching_schedule_template_coach')
                                ->where('coach_id', $coachId);
                        });
                });
            })
            ->groupBy('day_of_week', 'school_id', 'start_date')
            ->orderBy('day_of_week')
            ->orderBy('start_date')
            ->orderBy('school_id');

        $paginator = $keyQuery->paginate($perPage)->withQueryString();

        $keys = collect($paginator->items());

        if ($keys->isEmpty()) {
            return ['patterns' => collect(), 'paginator' => $paginator];
        }

        $templates = TeachingScheduleTemplate::with([
            'school', 'schoolClass', 'program', 'coach', 'additionalCoaches',
        ])
            ->withCount('sessions')
            ->where(function ($query) use ($keys) {
                foreach ($keys as $key) {
                    $query->orWhere(function ($inner) use ($key) {
                        $inner->where('day_of_week', (int) $key->day_of_week)
                            ->where('school_id', (int) $key->school_id)
                            ->whereDate('start_date', Carbon::parse($key->start_date)->toDateString());
                    });
                }
            })
            ->orderBy('start_time')
            ->get();

        $patterns = TeachingScheduleTemplate::groupIntoPatterns($templates)->map(function (array $pattern) {
            $pattern['session_count'] = (int) $pattern['rows']->sum('sessions_count');
            $pattern['meeting_total'] = (int) $pattern['rows']->sum('meeting_count');
            $pattern['detail_url'] = route('admin.schedules.pattern.show', [
                'day'    => $pattern['day_of_week'],
                'school' => $pattern['school']?->id,
                'start'  => $pattern['start_date']?->toDateString(),
            ]);

            return $pattern;
        });

        return ['patterns' => $patterns, 'paginator' => $paginator];
    }

    /**
     * Satu pola lengkap + sesi tergenerate (halaman detail).
     *
     * @return array{pattern: array<string, mixed>, sessions: Collection<int, TeachingSchedule>}|null
     */
    public function findPattern(User $user, int $day, int $schoolId, string $startDate): ?array
    {
        $templates = $this->scopePatternQuery(
            TeachingScheduleTemplate::query()->with(['school', 'schoolClass', 'program', 'coach', 'additionalCoaches']),
            $user
        )
            ->withCount('sessions')
            ->pattern($day, $schoolId, $startDate)
            ->orderBy('start_time')
            ->get();

        if ($templates->isEmpty()) {
            return null;
        }

        $pattern = TeachingScheduleTemplate::groupIntoPatterns($templates)->first();
        $pattern['session_count'] = (int) $templates->sum('sessions_count');

        $sessions = TeachingSchedule::with(['schoolClass', 'program', 'coach', 'additionalCoaches'])
            ->whereIn('template_id', $templates->pluck('id'))
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return ['pattern' => $pattern, 'sessions' => $sessions];
    }

    /**
     * Hapus seluruh pola (semua baris kelasnya). Sesi yang sudah digenerate
     * TETAP ADA — hanya tautan template_id yang dilepas (nullOnDelete).
     *
     * @return array{rows: int, sessions_kept: int}
     */
    public function deletePattern(User $user, int $day, int $schoolId, string $startDate): array
    {
        $this->assertPatternScope($user, $schoolId);

        $templates = TeachingScheduleTemplate::pattern($day, $schoolId, $startDate)->get();

        $sessionsKept = TeachingSchedule::whereIn('template_id', $templates->pluck('id'))->count();

        DB::transaction(function () use ($templates): void {
            foreach ($templates as $template) {
                $template->delete();
            }
        });

        return ['rows' => $templates->count(), 'sessions_kept' => $sessionsKept];
    }

    /**
     * Generate ulang semua baris dalam satu pola.
     *
     * @return array{created: int, skipped: int, warnings: array<int, string>}
     */
    public function generatePattern(User $user, int $day, int $schoolId, string $startDate): array
    {
        $this->assertPatternScope($user, $schoolId);

        $created = 0;
        $skipped = 0;
        $warnings = [];

        foreach (TeachingScheduleTemplate::with('additionalCoaches')->pattern($day, $schoolId, $startDate)->get() as $template) {
            $result = $this->generate($template, checkConflicts: true);
            $created += $result['created'];
            $skipped += $result['skipped'];
            $warnings = array_merge($warnings, $result['warnings']);
        }

        return ['created' => $created, 'skipped' => $skipped, 'warnings' => array_values(array_unique($warnings))];
    }

    // ------------------------------------------------------------------
    // Validasi internal
    // ------------------------------------------------------------------

    /**
     * Bentrok coach antar baris DI DALAM satu pengiriman. Dua pola dengan
     * tanggal mulai berbeda hanya bentrok bila himpunan tanggalnya beririsan.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, string>
     */
    private function payloadCoachConflicts(array $entries): array
    {
        $errors = [];

        foreach ($entries as $i => $a) {
            foreach ($entries as $j => $b) {
                if ($j <= $i) {
                    continue;
                }
                if ((int) $a['day_of_week'] !== (int) $b['day_of_week']) {
                    continue;
                }
                if ($b['start_time'] >= $a['end_time'] || $a['start_time'] >= $b['end_time']) {
                    continue;
                }
                if (array_intersect($a['coach_ids'], $b['coach_ids']) === []) {
                    continue;
                }
                if (array_intersect($a['schedule_dates'], $b['schedule_dates']) === []) {
                    // Tanggal mulai berbeda dan tidak ada tanggal yang sama.
                    continue;
                }

                $errors[] = "{$a['label']} dan {$b['label']}: coach yang sama bentrok jam pada hari yang sama.";
            }
        }

        return $errors;
    }

    /**
     * Kelas yang sama tidak boleh punya dua pola beririsan pada hari yang sama
     * (sesinya akan bertabrakan begitu digenerate), berapa pun tanggal
     * mulainya.
     */
    private function conflictingPatternExists(int $day, int $schoolId, int $classId, string $start, string $end): bool
    {
        return TeachingScheduleTemplate::query()
            ->where('day_of_week', $day)
            ->where('school_id', $schoolId)
            ->where('class_id', $classId)
            ->whereTime('start_time', '<', $end)
            ->whereTime('end_time', '>', $start)
            ->exists();
    }

    /**
     * Duplikat sesi level tanggal (sama dengan aturan CRUD/import existing).
     */
    private function sessionExists(TeachingScheduleTemplate $template, string $dateString): bool
    {
        $start = $this->padTime($template->start_time);
        $end = $this->padTime($template->end_time);

        if ($start === null || $end === null) {
            // Sesi tanpa jam tidak dianggap duplikat satu sama lain.
            return false;
        }

        return TeachingSchedule::query()
            ->where('school_id', $template->school_id)
            ->where('class_id', $template->class_id)
            ->whereDate('session_date', $dateString)
            ->whereTime('start_time', $start)
            ->whereTime('end_time', $end)
            ->exists();
    }

    /**
     * @param  array<int, int>  $coachIds
     */
    private function coachConflictOnDate(array $coachIds, string $dateString, ?string $startTime, ?string $endTime): bool
    {
        $startTime = $this->padTime($startTime);
        $endTime = $this->padTime($endTime);

        if ($startTime === null || $endTime === null) {
            return false;
        }

        return TeachingSchedule::query()
            ->whereDate('session_date', $dateString)
            ->whereTime('start_time', '<', $endTime)
            ->whereTime('end_time', '>', $startTime)
            ->where(function ($q) use ($coachIds) {
                $q->whereIn('coach_id', $coachIds)
                    ->orWhereHas('additionalCoaches', fn ($c) => $c->whereIn('coach_id', $coachIds));
            })
            ->exists();
    }

    // ------------------------------------------------------------------
    // Normalisasi nilai
    // ------------------------------------------------------------------

    /**
     * Dua tanggal terdekat yang jatuh pada hari pola — dipakai untuk memberi
     * saran konkret saat operator salah mengisi tanggal mulai.
     *
     * @return array<int, string>
     */
    private function nearestDates(Carbon $date, int $isoDay): array
    {
        $before = $date->copy();
        while ((int) $before->isoWeekday() !== $isoDay) {
            $before->subDay();
        }

        $after = $date->copy();
        while ((int) $after->isoWeekday() !== $isoDay) {
            $after->addDay();
        }

        return array_values(array_unique([
            $before->translatedFormat('d F Y'),
            $after->translatedFormat('d F Y'),
        ]));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseRequiredTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m) !== 1) {
            return null;
        }

        return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
    }

    private function parseOptionalTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $this->parseRequiredTime($value);
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * Jam apa pun (Carbon, "HH:MM", "HH:MM:SS") -> "HH:MM:SS" atau null.
     * whereTime (SQLite: strftime) membandingkan string penuh.
     */
    private function padTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $value, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        return $value;
    }

    private function scopedSchoolIds(User $user): ?array
    {
        if ($user->isSuperAdmin() || $user->isRelationUser()) {
            return null;
        }

        return $user->assignedSchoolIds();
    }

    /**
     * Scope query pola mengikuti aturan yang sama dengan modul jadwal:
     * SuperAdmin/Relation global, PIC School sekolah plot-nya, Coach hanya
     * pola yang melibatkannya. Role lain tidak punya akses jadwal.
     */
    private function scopePatternQuery($query, User $user)
    {
        if ($user->isSuperAdmin() || $user->isRelationUser()) {
            return $query;
        }

        if ($user->role === User::ROLE_SCHOOL_PIC) {
            return $query->whereIn('school_id', $user->assignedSchoolIds());
        }

        if ($user->role === User::ROLE_COACH) {
            return $query->where(function ($q) use ($user) {
                $q->where('coach_id', $user->id)
                    ->orWhereIn('id', function ($sub) use ($user) {
                        $sub->select('template_id')
                            ->from('teaching_schedule_template_coach')
                            ->where('coach_id', $user->id);
                    });
            });
        }

        abort(403, 'Anda tidak memiliki akses ke jadwal mengajar.');
    }

    /**
     * Pola yang boleh DIUBAH (manage): hanya role dengan scope sekolah.
     * Coach tidak pernah boleh mengubah konfigurasi pola.
     */
    private function assertPatternScope(User $user, int $schoolId): void
    {
        $schoolIds = $this->scopedSchoolIds($user);

        if ($schoolIds === null) {
            return;
        }

        if ($user->role === User::ROLE_COACH || ! in_array($schoolId, $schoolIds, true)) {
            abort(403, 'Pola ini di luar scope Anda.');
        }
    }
}
