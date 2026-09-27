<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Form pola jadwal DIGISchool (refactor 2026-09-25).
 *
 * Mengikuti alur workbook operasional: satu tab per HARI (satu sheet Excel),
 * di dalamnya banyak BLOK SEKOLAH, dan tiap blok punya TANGGAL MULAI serta
 * JUMLAH PERTEMUAN sendiri. Tidak ada `week_start` global — dua sekolah pada
 * hari yang sama boleh mulai di tanggal berbeda.
 *
 * Struktur payload: days[hari][blocks][blok][ ... ][rows][baris][ ... ].
 * Validasi otoritatif ada di ScheduleTemplateService::build(); bila satu blok
 * bermasalah, tidak ada pola yang tersimpan (atomik).
 */
class ScheduleBulkTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private School $schoolC;
    private SchoolClass $classA1;
    private SchoolClass $classA2;
    private SchoolClass $classB1;
    private SchoolClass $classC1;
    private Program $coding;
    private User $coachA;
    private User $coachB;
    private User $coachB1;
    private User $coachC;
    private User $relation;
    private User $superadmin;
    private User $picA;
    private User $coach;
    private User $finance;

    /** Senin, Rabu, dan Sabtu acuan pada minggu 2026-09-14. */
    private const MONDAY = '2026-09-14';
    private const WEDNESDAY = '2026-09-16';
    private const SATURDAY = '2026-09-19';

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Bulk A']);
        $this->schoolB = School::create(['name' => 'SD Bulk B']);
        $this->schoolC = School::create(['name' => 'SD Bulk C']);

        $this->classA1 = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 1A']);
        $this->classA2 = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 2A']);
        $this->classB1 = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 1B']);
        $this->classC1 = SchoolClass::create(['school_id' => $this->schoolC->id, 'name' => 'Grade 1C']);

        $this->coding = Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);
        $this->classA1->programs()->attach($this->coding->id);

        $this->coachA = $this->makeUser(User::ROLE_COACH, 'coach.a');
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA1->id]);

        $this->coachB = $this->makeUser(User::ROLE_COACH, 'coach.b');
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classA1->id]);
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classA2->id]);

        // Coach khusus kelas sekolah B dan C.
        $this->coachB1 = $this->makeUser(User::ROLE_COACH, 'coach.b1');
        CoachClass::create(['coach_id' => $this->coachB1->id, 'class_id' => $this->classB1->id]);

        $this->coachC = $this->makeUser(User::ROLE_COACH, 'coach.c');
        CoachClass::create(['coach_id' => $this->coachC->id, 'class_id' => $this->classC1->id]);

        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation');
        $this->superadmin = $this->makeUser(User::ROLE_SUPERADMIN, 'superadmin');
        $this->coach = $this->makeUser(User::ROLE_COACH, 'coach.plain');
        $this->finance = $this->makeUser(User::ROLE_FINANCE, 'finance');

        $this->picA = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.a');
        $this->picA->schools()->attach($this->schoolA->id);
    }

    // ===== Helper payload =====

    /**
     * Satu blok sekolah: nama sekolah + TANGGAL MULAI + JUMLAH PERTEMUAN
     * (keduanya milik pola ini, bukan periode global) + daftar baris kelas.
     */
    private function block(int $schoolId, array $rows, array $overrides = []): array
    {
        return array_merge([
            'school_id' => $schoolId,
            'start_date' => self::MONDAY,
            'meeting_count' => 2,
            'departure_location' => 'BSD',
            'departure_time' => '07:15',
            'arrival_time' => '07:45',
            'rows' => $rows,
        ], $overrides);
    }

    /**
     * Satu baris kelas pada blok sekolah. Field bersama (school_id, tanggal
     * mulai, keberangkatan) sengaja TIDAK diulang di sini.
     */
    private function row(int $classId, int $coachId, array $overrides = []): array
    {
        return array_merge([
            'class_id' => $classId,
            'program_id' => $this->coding->id,
            'coach_id' => $coachId,
            'start_time' => '08:00',
            'end_time' => '09:30',
            'student_count' => 10,
            'tools_dk' => 'Laptop 10',
            'tools_rk' => 'Box RK',
            'jalan_minggu_ini' => '1',
            'keterangan' => 'Sesi rutin',
        ], $overrides);
    }

    /**
     * Payload form. Tidak ada `week_start`: tanggal mulai ada di tiap blok.
     */
    private function payload(array $days): array
    {
        return ['days' => $days];
    }

    // ===== Tampilan form =====

    public function test_bulk_form_renders_day_navigation_and_helper_text(): void
    {
        $response = $this->actingAs($this->relation)
            ->get(route('admin.schedules.create'))
            ->assertOk();

        // Header dan navigasi hari Senin–Sabtu (satu tab = satu sheet Excel).
        $response->assertSee('Jadwal DIGISchool');
        foreach (['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU'] as $day) {
            $response->assertSee($day);
        }

        // Helper text operasional.
        $response->assertSee('Datang 30 Menit Sebelum Kelas di Mulai');

        // Aksi bulk.
        $response->assertSee('Tambah Sekolah');
        $response->assertSee('Tambah Kelas');
        $response->assertSee('Duplikat Blok');
        $response->assertSee('Copy Pola Hari');

        // Kolom mengikuti struktur operasional (bukan salinan Excel mentah).
        $response->assertSee('Lokasi Berangkat');
        $response->assertSee('Jam Berangkat');
        $response->assertSee('Jam Sampai');
        $response->assertSee('Tools DK');
        $response->assertSee('Tools RK');
        $response->assertSee('Minggu Ini?');
        $response->assertSee('KET');
    }

    public function test_bulk_form_asks_for_start_date_and_meeting_count_per_school(): void
    {
        $response = $this->actingAs($this->relation)
            ->get(route('admin.schedules.create'))
            ->assertOk();

        // Dua field ini ada di TINGKAT BLOK SEKOLAH — bukan sekali untuk
        // seluruh form, karena setiap sekolah punya tanggal mulai sendiri.
        $response->assertSee('Tanggal Mulai');
        $response->assertSee('Jumlah Pertemuan');
        $response->assertSee('data-field="start_date"', false);
        $response->assertSee('data-field="meeting_count"', false);

        // Default 20 pertemuan tetap ada.
        $response->assertSee('value="20"', false);
    }

    public function test_bulk_form_has_no_global_week_start_field(): void
    {
        $response = $this->actingAs($this->relation)
            ->get(route('admin.schedules.create'))
            ->assertOk();

        $response->assertDontSee('name="week_start"', false);
    }

    public function test_bulk_form_open_on_requested_day(): void
    {
        $response = $this->actingAs($this->relation)
            ->get(route('admin.schedules.create', ['day' => 3]))
            ->assertOk();

        $response->assertSee('dayPanel3', false);
        $response->assertSee('dayTab3', false);
    }

    // ===== Simpan bulk =====

    public function test_bulk_saves_multiple_schools_classes_and_coaches_across_week(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [ // Senin
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, ['start_time' => '08:00', 'end_time' => '09:30']),
                            $this->row($this->classA2->id, $this->coachB->id, ['start_time' => '10:00', 'end_time' => '11:30']),
                        ]),
                    ],
                ],
                3 => [ // Rabu — sekolah kedua, coach berbeda
                    'blocks' => [
                        $this->block($this->schoolB->id, [
                            $this->row($this->classB1->id, $this->coachB1->id, ['start_time' => '13:00', 'end_time' => '14:30']),
                        ], [
                            'start_date' => self::WEDNESDAY,
                            'meeting_count' => 3,
                            'departure_location' => 'Bintaro',
                            'departure_time' => '12:00',
                            'arrival_time' => '12:45',
                        ]),
                    ],
                ],
                6 => [ // Sabtu
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, ['start_time' => '09:00', 'end_time' => '10:30']),
                        ], ['start_date' => self::SATURDAY, 'meeting_count' => 1]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola'])
            ->assertSessionHas('success');

        // 3 pola (Senin-A, Rabu-B, Sabtu-A) berisi 4 baris kelas;
        // sesi = 2 + 2 + 3 + 1 = 8.
        $this->assertSame(4, TeachingScheduleTemplate::count());
        $this->assertSame(
            3,
            TeachingScheduleTemplate::groupIntoPatterns(TeachingScheduleTemplate::all())->count()
        );
        $this->assertSame(8, TeachingSchedule::count());

        // Tanggal tiap hari dihitung dari TANGGAL MULAI pola itu sendiri.
        $monday = TeachingSchedule::whereDate('session_date', self::MONDAY)->get();
        $this->assertCount(2, $monday);
        $this->assertSame([1], $monday->pluck('day_of_week')->unique()->all());

        $wednesday = TeachingSchedule::whereDate('session_date', self::WEDNESDAY)->firstOrFail();
        $this->assertSame($this->schoolB->id, $wednesday->school_id);
        $this->assertSame('Bintaro', $wednesday->departure_location);
        $this->assertSame('12:00', $wednesday->departure_time->format('H:i'));
        $this->assertSame('12:45', $wednesday->arrival_time->format('H:i'));

        $saturday = TeachingSchedule::whereDate('session_date', self::SATURDAY)->firstOrFail();
        $this->assertSame(6, $saturday->day_of_week);
        $this->assertSame('09:00', $saturday->start_time->format('H:i'));

        // Field baris tersimpan lengkap.
        $first = $monday->firstWhere('class_id', $this->classA1->id);
        $this->assertSame($this->coachA->id, $first->coach_id);
        $this->assertSame($this->coding->id, $first->program_id);
        $this->assertSame(10, $first->student_count);
        $this->assertSame('Laptop 10', $first->tools_dk);
        $this->assertSame('Box RK', $first->tools_rk);
        $this->assertTrue($first->jalan_minggu_ini);
        $this->assertSame('Sesi rutin', $first->keterangan);
        // Field tingkat sekolah tidak diulang per baris, tetap terisi dari blok.
        $this->assertSame('BSD', $first->departure_location);
        $this->assertSame('07:15', $first->departure_time->format('H:i'));

        // Tautan balik ke pola tersimpan.
        $this->assertNotNull($first->template_id);
        $this->assertSame(1, $first->meeting_number);
    }

    public function test_bulk_supports_additional_coaches_per_row(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, [
                                'additional_coaches' => [$this->coachB->id],
                            ]),
                        ], ['meeting_count' => 1]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $schedule = TeachingSchedule::firstOrFail();
        $this->assertSame([$this->coachB->id], $schedule->additionalCoaches->pluck('id')->all());

        // Coach tambahan tersimpan di pola juga, bukan hanya di sesi.
        $template = TeachingScheduleTemplate::firstOrFail();
        $this->assertSame([$this->coachB->id], $template->additionalCoaches->pluck('id')->all());
    }

    public function test_jalan_minggu_ini_false_is_preserved_and_does_not_break_the_pattern(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, [
                                'jalan_minggu_ini' => '0',
                            ]),
                        ], ['meeting_count' => 2]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        // §4: status mingguan adalah status operasional, bukan definisi pola —
        // menandai "tidak jalan" tidak boleh menghapus/mengubah pola berulang.
        $this->assertSame(1, TeachingScheduleTemplate::count());
        $template = TeachingScheduleTemplate::firstOrFail();
        $this->assertFalse($template->jalan_minggu_ini);
        $this->assertSame(self::MONDAY, $template->start_date->toDateString());
        $this->assertSame(2, $template->meeting_count);

        // Kedua pertemuan tetap tergenerate.
        $this->assertSame(2, TeachingSchedule::count());
        $this->assertSame(2, TeachingSchedule::where('jalan_minggu_ini', false)->count());
    }

    public function test_empty_rows_are_skipped_not_errors(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, ['student_count' => 4]),
                            ['class_id' => '', 'coach_id' => ''], // baris belum diisi
                        ], ['meeting_count' => 1]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $this->assertSame(1, TeachingSchedule::count());
        $this->assertSame(1, TeachingScheduleTemplate::count());
    }

    public function test_bulk_with_no_filled_row_is_rejected(): void
    {
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [['class_id' => '', 'coach_id' => '']]),
                    ],
                ],
            ]))
            ->assertSessionHas('error');

        $this->assertSame(0, TeachingSchedule::count());
        $this->assertSame(0, TeachingScheduleTemplate::count());
    }

    // ===== §3: generate per sekolah, tanpa asumsi periode global =====

    public function test_three_schools_on_same_day_each_use_their_own_start_date(): void
    {
        $lecStart = '2026-08-03';       // Senin
        $penaburStart = '2026-08-10';   // Senin
        $globalStart = '2026-09-14';    // Senin (tanggal "minggu" yang lama)

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, ['start_time' => '08:00', 'end_time' => '09:00']),
                        ], ['start_date' => $lecStart, 'meeting_count' => 20]),
                        $this->block($this->schoolB->id, [
                            $this->row($this->classB1->id, $this->coachB1->id, ['start_time' => '10:00', 'end_time' => '11:00']),
                        ], ['start_date' => $penaburStart, 'meeting_count' => 20]),
                        $this->block($this->schoolC->id, [
                            $this->row($this->classC1->id, $this->coachC->id, ['start_time' => '13:00', 'end_time' => '14:00']),
                        ], ['start_date' => $globalStart, 'meeting_count' => 20]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        // Tiga pola terpisah pada hari yang sama.
        $this->assertSame(3, TeachingScheduleTemplate::count());
        $this->assertSame(60, TeachingSchedule::count());

        $schoolA = TeachingSchedule::where('school_id', $this->schoolA->id)->orderBy('meeting_number')->get();
        $schoolB = TeachingSchedule::where('school_id', $this->schoolB->id)->orderBy('meeting_number')->get();
        $schoolC = TeachingSchedule::where('school_id', $this->schoolC->id)->orderBy('meeting_number')->get();

        $this->assertCount(20, $schoolA);
        $this->assertCount(20, $schoolB);
        $this->assertCount(20, $schoolC);

        // Pertemuan ke-1 tiap sekolah = tanggal mulai sekolah itu sendiri.
        $this->assertSame($lecStart, $schoolA->first()->session_date->toDateString());
        $this->assertSame($penaburStart, $schoolB->first()->session_date->toDateString());
        $this->assertSame($globalStart, $schoolC->first()->session_date->toDateString());

        // §3 contoh eksplisit: LEC 3 Agu, Penabur GS 10 Agu.
        $this->assertSame('2026-08-03', $schoolA->firstWhere('meeting_number', 1)->session_date->toDateString());
        $this->assertSame('2026-08-10', $schoolB->firstWhere('meeting_number', 1)->session_date->toDateString());

        // Nomor pertemuan dihitung per pola, bukan per hari global.
        $this->assertSame(range(1, 20), $schoolA->pluck('meeting_number')->all());
        $this->assertSame(range(1, 20), $schoolB->pluck('meeting_number')->all());
        $this->assertSame(range(1, 20), $schoolC->pluck('meeting_number')->all());

        // Pertemuan terakhir = tanggal mulai + 19 minggu.
        $this->assertSame(
            Carbon::parse($lecStart)->addWeeks(19)->toDateString(),
            $schoolA->last()->session_date->toDateString()
        );
        $this->assertSame(
            Carbon::parse($penaburStart)->addWeeks(19)->toDateString(),
            $schoolB->last()->session_date->toDateString()
        );

        // Semua sesi tetap jatuh pada hari Senin.
        foreach ([$schoolA, $schoolB, $schoolC] as $group) {
            $this->assertSame([1], $group->pluck('day_of_week')->unique()->all());
        }

        // §3: TIDAK ADA asumsi tanggal mulai global. Sekolah B dan C tidak
        // boleh punya sesi pada tanggal mulai sekolah A.
        $this->assertSame(0, TeachingSchedule::where('school_id', $this->schoolB->id)
            ->whereDate('session_date', $lecStart)->count());
        $this->assertSame(0, TeachingSchedule::where('school_id', $this->schoolC->id)
            ->whereDate('session_date', $lecStart)->count());

        // Tanggal sesi kedua sekolah berbeda walaupun harinya sama.
        $this->assertNotSame(
            $schoolA->first()->session_date->toDateString(),
            $schoolB->first()->session_date->toDateString()
        );
    }

    public function test_each_day_of_week_is_scheduled_independently(): void
    {
        // Selasa 2026-09-15 dan Rabu 2026-09-16 punya pola sendiri.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                2 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id),
                        ], ['start_date' => '2026-09-15', 'meeting_count' => 2]),
                    ],
                ],
                3 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA2->id, $this->coachB->id),
                        ], ['start_date' => '2026-09-16', 'meeting_count' => 2]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $this->assertSame(2, TeachingScheduleTemplate::count());
        $this->assertSame(4, TeachingSchedule::count());

        $tuesday = TeachingSchedule::where('day_of_week', 2)->orderBy('session_date')->get();
        $wednesday = TeachingSchedule::where('day_of_week', 3)->orderBy('session_date')->get();

        $this->assertSame(['2026-09-15', '2026-09-22'], $tuesday->pluck('session_date')
            ->map(fn ($date) => $date->toDateString())->all());
        $this->assertSame(['2026-09-16', '2026-09-23'], $wednesday->pluck('session_date')
            ->map(fn ($date) => $date->toDateString())->all());
    }

    public function test_meeting_numbers_restart_per_school_pattern(): void
    {
        // Dua sekolah pada hari yang sama dengan jumlah pertemuan berbeda.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, ['start_time' => '08:00', 'end_time' => '09:00']),
                        ], ['start_date' => '2026-08-03', 'meeting_count' => 5]),
                        $this->block($this->schoolB->id, [
                            $this->row($this->classB1->id, $this->coachB1->id, ['start_time' => '10:00', 'end_time' => '11:00']),
                        ], ['start_date' => '2026-08-10', 'meeting_count' => 3]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $this->assertSame(
            [1, 2, 3, 4, 5],
            TeachingSchedule::where('school_id', $this->schoolA->id)
                ->orderBy('session_date')->pluck('meeting_number')->all()
        );
        $this->assertSame(
            [1, 2, 3],
            TeachingSchedule::where('school_id', $this->schoolB->id)
                ->orderBy('session_date')->pluck('meeting_number')->all()
        );
    }

    public function test_multiple_classes_in_one_school_block_share_the_pattern_start_date(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, ['start_time' => '08:00', 'end_time' => '09:00']),
                            $this->row($this->classA2->id, $this->coachB->id, ['start_time' => '09:00', 'end_time' => '10:00']),
                        ], ['start_date' => '2026-08-03', 'meeting_count' => 4]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        // Satu pola (hari + sekolah + tanggal mulai) berisi dua baris kelas.
        $patterns = TeachingScheduleTemplate::groupIntoPatterns(TeachingScheduleTemplate::all());
        $this->assertCount(1, $patterns);
        $this->assertSame(2, $patterns->first()['rows']->count());

        // Kedua kelas punya tanggal dan nomor pertemuan yang sama.
        foreach ([$this->classA1->id, $this->classA2->id] as $classId) {
            $sessions = TeachingSchedule::where('class_id', $classId)->orderBy('session_date')->get();
            $this->assertCount(4, $sessions);
            $this->assertSame('2026-08-03', $sessions->first()->session_date->toDateString());
            $this->assertSame([1, 2, 3, 4], $sessions->pluck('meeting_number')->all());
        }
    }

    public function test_start_date_must_match_the_day_of_the_tab(): void
    {
        // 2026-09-15 adalah Selasa, ditempel pada tab SENIN (hari 1).
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id),
                        ], ['start_date' => '2026-09-15']),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        // §6: tanggal tidak valid ditolak dan dilaporkan, TIDAK digeser
        // diam-diam ke hari yang cocok.
        $this->assertSame(0, TeachingScheduleTemplate::count());
        $this->assertSame(0, TeachingSchedule::count());

        $errors = session('errors')->get('schedules');
        $this->assertStringContainsString('bukan hari SENIN', $errors[0]);
    }

    public function test_missing_start_date_is_reported_not_invented(): void
    {
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id),
                        ], ['start_date' => '']),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        // Pesan menyebut kolom KET, sumber tanggal mulai pada workbook asli.
        $errors = session('errors')->get('schedules');
        $this->assertStringContainsString('tanggal mulai wajib diisi', $errors[0]);
        $this->assertStringContainsString('KET', $errors[0]);
        $this->assertSame(0, TeachingScheduleTemplate::count());
    }

    public function test_same_school_twice_on_one_day_with_the_same_start_date_is_rejected(): void
    {
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, ['start_time' => '08:00', 'end_time' => '09:00']),
                        ]),
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA2->id, $this->coachB->id, ['start_time' => '10:00', 'end_time' => '11:00']),
                        ]),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        $this->assertSame(0, TeachingScheduleTemplate::count());

        $errors = session('errors')->get('schedules');
        $this->assertStringContainsString('tanggal mulai yang sama', $errors[0]);
    }

    public function test_same_school_twice_on_one_day_with_different_start_dates_is_allowed(): void
    {
        // Dua pola Senin untuk sekolah yang sama, mulai di tanggal berbeda —
        // inilah yang dulu tidak mungkin dengan satu periode global.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, ['start_time' => '08:00', 'end_time' => '09:00']),
                        ], ['start_date' => '2026-08-03', 'meeting_count' => 2]),
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA2->id, $this->coachB->id, ['start_time' => '10:00', 'end_time' => '11:00']),
                        ], ['start_date' => '2026-10-05', 'meeting_count' => 2]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $this->assertSame(2, TeachingScheduleTemplate::count());
        $this->assertSame(4, TeachingSchedule::count());
        $this->assertSame('2026-08-03', TeachingSchedule::where('class_id', $this->classA1->id)
            ->orderBy('session_date')->first()->session_date->toDateString());
        $this->assertSame('2026-10-05', TeachingSchedule::where('class_id', $this->classA2->id)
            ->orderBy('session_date')->first()->session_date->toDateString());
    }

    // ===== Validasi dependen (aturan bisnis yang sama dengan form satuan) =====

    public function test_class_must_belong_to_the_block_school(): void
    {
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        // Kelas sekolah B ditempel pada blok sekolah A.
                        $this->block($this->schoolA->id, [
                            $this->row($this->classB1->id, $this->coachA->id),
                        ]),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        $this->assertSame(0, TeachingSchedule::count());
    }

    public function test_coach_must_be_assigned_to_the_class(): void
    {
        // Coach A tidak ter-assign ke kelas sekolah B pada payload ini.
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolB->id, [
                            $this->row($this->classB1->id, $this->coachA->id),
                        ]),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        $this->assertSame(0, TeachingSchedule::count());
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, [
                                'start_time' => '10:00', 'end_time' => '09:00',
                            ]),
                        ]),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        $this->assertSame(0, TeachingSchedule::count());
    }

    public function test_duplicate_session_inside_one_submission_is_rejected(): void
    {
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            // Dua baris identik pada hari yang sama.
                            $this->row($this->classA1->id, $this->coachA->id),
                            $this->row($this->classA1->id, $this->coachA->id),
                        ]),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        $this->assertSame(0, TeachingSchedule::count());
    }

    public function test_coach_conflict_within_one_submission_is_rejected(): void
    {
        // Coach A ter-assign ke dua kelas sekolah A; dua baris pada hari yang
        // sama dengan jam beririsan harus ditolak.
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA2->id]);

        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, [
                                'start_time' => '08:00', 'end_time' => '09:30',
                            ]),
                            $this->row($this->classA2->id, $this->coachA->id, [
                                'start_time' => '09:00', 'end_time' => '10:30',
                            ]),
                        ]),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        $this->assertSame(0, TeachingSchedule::count());
    }

    public function test_coach_conflict_across_blocks_is_rejected_when_dates_overlap(): void
    {
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA2->id]);

        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, [
                                'start_time' => '08:00', 'end_time' => '09:30',
                            ]),
                        ], ['start_date' => '2026-08-03', 'meeting_count' => 4]),
                        $this->block($this->schoolB->id, [
                            $this->row($this->classA2->id, $this->coachA->id, [
                                'start_time' => '09:00', 'end_time' => '10:30',
                            ]),
                        ], ['start_date' => '2026-08-17', 'meeting_count' => 4]),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        $this->assertSame(0, TeachingSchedule::count());
    }

    public function test_coach_conflict_across_blocks_is_allowed_when_dates_never_overlap(): void
    {
        // Dua pola Senin dengan tanggal mulai berbeda sehingga tidak ada satu
        // pun tanggal pertemuan yang bertabrakan — bukan konflik.
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA2->id]);

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, [
                                'start_time' => '08:00', 'end_time' => '09:30',
                            ]),
                        ], ['start_date' => '2026-08-03', 'meeting_count' => 2]),
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA2->id, $this->coachA->id, [
                                'start_time' => '09:00', 'end_time' => '10:30',
                            ]),
                        ], ['start_date' => '2026-09-07', 'meeting_count' => 2]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $this->assertSame(4, TeachingSchedule::count());
    }

    public function test_non_overlapping_coach_sessions_in_one_block_are_allowed(): void
    {
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA2->id]);

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, [
                                'start_time' => '08:00', 'end_time' => '09:30',
                            ]),
                            $this->row($this->classA2->id, $this->coachA->id, [
                                'start_time' => '09:30', 'end_time' => '11:00',
                            ]),
                        ], ['meeting_count' => 1]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $this->assertSame(2, TeachingSchedule::count());
    }

    public function test_one_invalid_block_blocks_the_whole_submission_atomically(): void
    {
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id),
                        ]),
                    ],
                ],
                3 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            // Blok rusak: kelas sekolah B pada blok sekolah A.
                            $this->row($this->classB1->id, $this->coachA->id),
                        ], ['start_date' => self::WEDNESDAY]),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        // Blok Senin yang valid pun tidak tersimpan.
        $this->assertSame(0, TeachingSchedule::count());
        $this->assertSame(0, TeachingScheduleTemplate::count());
    }

    public function test_error_message_names_the_day_block_and_row(): void
    {
        $response = $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                3 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, [
                                'end_time' => '07:00',
                            ]),
                        ], ['start_date' => self::WEDNESDAY]),
                    ],
                ],
            ]));

        $response->assertSessionHasErrors('schedules');
        $errors = session('errors')->get('schedules');
        $this->assertStringContainsString('RABU blok 1', $errors[0]);
    }

    // ===== Idempotensi / generate ulang =====

    public function test_generating_the_same_pattern_twice_creates_no_duplicates(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id),
                        ], ['meeting_count' => 5]),
                    ],
                ],
            ]));

        $this->assertSame(5, TeachingSchedule::count());

        // Generate ulang lewat aksi tingkat pola — tidak ada sesi baru.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.pattern.generate'), [
                'day' => 1,
                'school_id' => $this->schoolA->id,
                'start_date' => self::MONDAY,
            ])
            ->assertRedirect();

        $this->assertSame(5, TeachingSchedule::count());
        $this->assertSame(
            range(1, 5),
            TeachingSchedule::orderBy('session_date')->pluck('meeting_number')->all()
        );
    }

    public function test_posting_the_same_block_twice_does_not_duplicate_sessions(): void
    {
        $payload = $this->payload([
            1 => [
                'blocks' => [
                    $this->block($this->schoolA->id, [
                        $this->row($this->classA1->id, $this->coachA->id),
                    ], ['meeting_count' => 3]),
                ],
            ],
        ]);

        $this->actingAs($this->relation)->post(route('admin.schedules.bulk.store'), $payload);

        // Pengiriman kedua: kelas + jam yang sama sudah punya pola beririsan.
        $this->actingAs($this->relation)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $payload)
            ->assertSessionHasErrors('schedules');

        $this->assertSame(3, TeachingSchedule::count());
        $this->assertSame(1, TeachingScheduleTemplate::count());
    }

    // ===== Otorisasi & scope =====

    public function test_pic_can_bulk_store_within_own_school(): void
    {
        $this->actingAs($this->picA)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id),
                        ], ['meeting_count' => 1]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $this->assertSame(1, TeachingSchedule::count());
        $this->assertSame($this->schoolA->id, TeachingSchedule::first()->school_id);
    }

    public function test_pic_cannot_bulk_store_outside_scope(): void
    {
        $this->actingAs($this->picA)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolB->id, [
                            $this->row($this->classB1->id, $this->coachB1->id),
                        ]),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('schedules');

        $this->assertSame(0, TeachingSchedule::count());
        $errors = session('errors')->get('schedules');
        $this->assertStringContainsString('di luar scope', $errors[0]);
    }

    public function test_pic_only_sees_own_schools_in_the_form(): void
    {
        $response = $this->actingAs($this->picA)
            ->get(route('admin.schedules.create'))
            ->assertOk();

        $this->assertStringContainsString($this->schoolA->name, $response->getContent());
        $this->assertStringNotContainsString($this->schoolB->name, $response->getContent());
    }

    public function test_coach_and_finance_cannot_bulk_store(): void
    {
        foreach ([$this->coach, $this->finance] as $user) {
            $this->actingAs($user)
                ->get(route('admin.schedules.create'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.schedules.bulk.store'), $this->payload([
                    1 => [
                        'blocks' => [
                            $this->block($this->schoolA->id, [
                                $this->row($this->classA1->id, $this->coachA->id),
                            ]),
                        ],
                    ],
                ]))
                ->assertForbidden();
        }

        $this->assertSame(0, TeachingSchedule::count());
    }

    public function test_superadmin_can_bulk_store(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id),
                        ], ['meeting_count' => 1]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $this->assertSame(1, TeachingSchedule::count());
    }

    public function test_bulk_route_requires_authentication(): void
    {
        $this->get(route('admin.schedules.create'))->assertRedirect(route('login'));
        $this->post(route('admin.schedules.bulk.store'), [])->assertRedirect(route('login'));
    }

    // ===== Data master dependen =====

    public function test_class_students_endpoint_returns_roster_count(): void
    {
        Student::create(['class_id' => $this->classA1->id, 'name' => 'Murid Satu']);
        Student::create(['class_id' => $this->classA1->id, 'name' => 'Murid Dua']);

        $this->actingAs($this->relation)
            ->getJson(route('admin.schedules.class-students', $this->classA1))
            ->assertOk()
            ->assertJsonPath('class_id', $this->classA1->id)
            ->assertJsonPath('school_id', $this->schoolA->id)
            ->assertJsonPath('count', 2);
    }

    public function test_pic_cannot_read_roster_outside_scope(): void
    {
        $this->actingAs($this->picA)
            ->getJson(route('admin.schedules.class-students', $this->classB1))
            ->assertForbidden();
    }

    // ===== Kompatibilitas fitur yang sudah ada =====

    public function test_bulk_creates_one_pattern_per_school_day_block(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id, ['start_time' => '08:00', 'end_time' => '09:00']),
                            $this->row($this->classA2->id, $this->coachB->id, ['start_time' => '10:00', 'end_time' => '11:00']),
                        ], ['meeting_count' => 1]),
                    ],
                ],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        // Satu pola, dua baris kelas — bukan dua pola terpisah.
        $this->assertSame(2, TeachingScheduleTemplate::count());
        $this->assertSame(1, TeachingScheduleTemplate::groupIntoPatterns(TeachingScheduleTemplate::all())->count());
        $this->assertSame(2, TeachingSchedule::count());
    }

    public function test_pattern_detail_page_lists_generated_sessions(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id),
                        ], ['meeting_count' => 3]),
                    ],
                ],
            ]));

        $this->actingAs($this->relation)
            ->get(route('admin.schedules.pattern.show', [
                'day' => 1,
                'school' => $this->schoolA->id,
                'start' => self::MONDAY,
            ]))
            ->assertOk()
            ->assertSee($this->schoolA->name)
            ->assertSee('3 pertemuan')
            ->assertSee('Pertemuan Tergenerate');
    }

    public function test_pattern_detail_404s_for_unknown_pattern(): void
    {
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.pattern.show', [
                'day' => 1,
                'school' => $this->schoolA->id,
                'start' => '2030-01-07',
            ]))
            ->assertNotFound();
    }

    public function test_pic_cannot_open_another_schools_pattern_detail(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolB->id, [
                            $this->row($this->classB1->id, $this->coachB1->id),
                        ], ['meeting_count' => 1]),
                    ],
                ],
            ]));

        $this->actingAs($this->picA)
            ->get(route('admin.schedules.pattern.show', [
                'day' => 1,
                'school' => $this->schoolB->id,
                'start' => self::MONDAY,
            ]))
            ->assertNotFound();
    }

    public function test_deleting_a_pattern_keeps_its_generated_sessions(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $this->payload([
                1 => [
                    'blocks' => [
                        $this->block($this->schoolA->id, [
                            $this->row($this->classA1->id, $this->coachA->id),
                        ], ['meeting_count' => 3]),
                    ],
                ],
            ]));

        $this->assertSame(3, TeachingSchedule::count());

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.pattern.destroy'), [
                'day' => 1,
                'school_id' => $this->schoolA->id,
                'start_date' => self::MONDAY,
            ])
            ->assertRedirect();

        // §9: sesi yang sudah tergenerate tetap aman.
        $this->assertSame(0, TeachingScheduleTemplate::count());
        $this->assertSame(3, TeachingSchedule::count());
        $this->assertSame(0, TeachingSchedule::whereNotNull('template_id')->count());
    }

    private function makeUser(string $role, string $slug): User
    {
        return User::create([
            'name' => ucfirst($slug).' Test',
            'email' => $slug.'@test.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
