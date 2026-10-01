<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Siklus hidup pola jadwal per HARI + SEKOLAH (refactor 2026-09-25).
 *
 * Melanjutkan ScheduleBulkTest dari sisi sebaliknya: bukan cara memasukkan
 * pola, tetapi apa yang terjadi SESUDAHNYA —
 *
 * - atribut baris pola tersalin lengkap ke sesi hasil generate;
 * - default 20 pertemuan saat jumlah pertemuan tidak diisi;
 * - sesi yang bentrok jadwal coach dilewati dan dilaporkan, bukan dipaksa;
 * - libur = hapus sesi individual; generate ulang hanya mengisi yang kurang;
 * - hapus pola/baris pola TIDAK menghapus sesi (data bisnis aktif tetap aman);
 * - scope PIC & larangan role tanpa akses jadwal;
 * - reminder laporan mengikuti TANGGAL SESI hasil generate tiap sekolah,
 *   bukan satu periode global.
 */
class SemesterScheduleTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classA2;
    private SchoolClass $classB;
    private User $coach;
    private User $coachExtra;
    private User $coachB;
    private User $relation;
    private User $superadmin;
    private User $picA;
    private User $finance;
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Test A']);
        $this->schoolB = School::create(['name' => 'SD Test B']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 1A']);
        $this->classA2 = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 2A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 1B']);

        $this->program = Program::create(['name' => 'Coding Kids', 'code' => 'COD', 'status' => 'active']);

        $this->coach = User::create([
            'name' => 'Coach Satu', 'email' => 'coach.satu@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->classA2->id]);

        $this->coachExtra = User::create([
            'name' => 'Coach Dua', 'email' => 'coach.dua@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coachExtra->id, 'class_id' => $this->classA->id]);

        $this->coachB = User::create([
            'name' => 'Coach B', 'email' => 'coach.b@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classB->id]);

        $this->relation = User::create([
            'name' => 'Relation Test', 'email' => 'relation@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_RELATION,
        ]);

        $this->superadmin = User::create([
            'name' => 'Super Admin', 'email' => 'superadmin@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_SUPERADMIN,
        ]);

        $this->picA = User::create([
            'name' => 'PIC A', 'email' => 'pic.a@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_SCHOOL_PIC,
        ]);
        $this->picA->schools()->attach($this->schoolA->id);

        $this->finance = User::create([
            'name' => 'Finance', 'email' => 'finance@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_FINANCE,
        ]);
    }

    /**
     * Dua pola pada hari berbeda, masing-masing dengan TANGGAL MULAI dan
     * JUMLAH PERTEMUAN sendiri:
     * - SENIN sekolah A, mulai 2026-09-14, 4 pertemuan, 2 kelas + coach tambahan;
     * - SELASA sekolah B, mulai 2026-09-15, 4 pertemuan, 1 kelas.
     */
    private function patternPayload(): array
    {
        return ['days' => [
            1 => ['blocks' => [[
                'school_id' => $this->schoolA->id,
                'start_date' => '2026-09-14',
                'meeting_count' => 4,
                'departure_location' => 'BSD',
                'departure_time' => '07:15',
                'arrival_time' => '07:45',
                'rows' => [
                    [
                        'class_id' => $this->classA->id,
                        'program_id' => $this->program->id,
                        'coach_id' => $this->coach->id,
                        'additional_coaches' => [$this->coachExtra->id],
                        'start_time' => '08:00',
                        'end_time' => '09:30',
                        'student_count' => 12,
                        'tools_dk' => 'Laptop 12',
                        'tools_rk' => 'Box RK A',
                        'jalan_minggu_ini' => '1',
                        'topic' => 'Topik A',
                        'keterangan' => 'Ket A',
                    ],
                    [
                        'class_id' => $this->classA2->id,
                        'program_id' => null,
                        'coach_id' => $this->coach->id,
                        'additional_coaches' => [],
                        'start_time' => '09:30',
                        'end_time' => '11:00',
                    ],
                ],
            ]]],
            2 => ['blocks' => [[
                'school_id' => $this->schoolB->id,
                'start_date' => '2026-09-15',
                'meeting_count' => 4,
                'rows' => [[
                    'class_id' => $this->classB->id,
                    'program_id' => null,
                    'coach_id' => $this->coachB->id,
                    'additional_coaches' => [],
                    'start_time' => '10:00',
                    'end_time' => '11:00',
                ]],
            ]]],
        ]];
    }

    private function createPatterns(array $payload = null)
    {
        return $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), $payload ?? $this->patternPayload());
    }

    // ===== Rute lama "Jadwal Semester" sudah pensiun =====

    public function test_retired_semester_builder_redirects_to_the_day_form(): void
    {
        // §3: tidak ada lagi periode global — membuka builder lama mengarahkan
        // operator ke form per hari + per sekolah.
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.semester'))
            ->assertRedirectToRoute('admin.schedules.create');

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.semester.store'), [
                'period_name' => 'Semester Ganjil',
                'period_start' => '2026-09-14',
                'period_end' => '2027-03-01',
                'meetings_target' => 20,
            ])
            ->assertRedirectToRoute('admin.schedules.create');

        // Tidak ada pola yang lahir dari payload periode global.
        $this->assertSame(0, TeachingScheduleTemplate::count());
        $this->assertSame(0, TeachingSchedule::count());
    }

    // ===== Sesi hasil generate =====

    public function test_generated_sessions_copy_every_pattern_attribute(): void
    {
        $this->createPatterns()
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola'])
            ->assertSessionHas('success');

        // 3 baris pola (2 Senin + 1 Selasa), masing-masing 4 pertemuan.
        $this->assertSame(3, TeachingScheduleTemplate::count());
        $this->assertSame(12, TeachingSchedule::count());

        $template = TeachingScheduleTemplate::where('class_id', $this->classA->id)->firstOrFail();
        $this->assertSame(1, $template->day_of_week);
        $this->assertSame('2026-09-14', $template->start_date->toDateString());
        $this->assertSame(4, $template->meeting_count);
        $this->assertSame('BSD', $template->departure_location);
        $this->assertSame('07:15', $template->departure_time->format('H:i'));
        $this->assertSame([$this->coachExtra->id], $template->additionalCoaches->pluck('id')->all());

        // Sesi Senin kelas 1A: 4 pertemuan pada tanggal berulang mingguan.
        $sessions = $template->sessions()->orderBy('session_date')->get();
        $this->assertSame(
            ['2026-09-14', '2026-09-21', '2026-09-28', '2026-10-05'],
            $sessions->pluck('session_date')->map->toDateString()->all(),
        );
        $this->assertSame([1, 2, 3, 4], $sessions->pluck('meeting_number')->all());
        $this->assertTrue($sessions->every(fn ($session) => $session->day_of_week === 1));

        $first = $sessions->first();
        $this->assertSame($this->schoolA->id, $first->school_id);
        $this->assertSame($this->program->id, $first->program_id);
        $this->assertSame($this->coach->id, $first->coach_id);
        $this->assertSame('08:00', $first->start_time->format('H:i'));
        $this->assertSame('09:30', $first->end_time->format('H:i'));
        $this->assertSame(12, $first->student_count);
        $this->assertSame('Laptop 12', $first->tools_dk);
        $this->assertSame('Topik A', $first->topic);

        // Coach tambahan tersalin ke semua sesi hasil generate.
        $this->assertTrue($sessions->every(
            fn ($session) => $session->additionalCoaches->pluck('id')->all() === [$this->coachExtra->id]
        ));

        // Sesi Selasa sekolah B mengikuti tanggal mulai sekolah B.
        $tuesday = TeachingSchedule::where('class_id', $this->classB->id)
            ->orderBy('session_date')->get();
        $this->assertSame(
            ['2026-09-15', '2026-09-22', '2026-09-29', '2026-10-06'],
            $tuesday->pluck('session_date')->map->toDateString()->all(),
        );

        // Kelas 2A (coach sama, jam tidak beririsan) tetap tergenerate.
        $this->assertSame(4, TeachingSchedule::where('class_id', $this->classA2->id)->count());
    }

    public function test_default_meeting_count_is_twenty_per_pattern(): void
    {
        $payload = $this->patternPayload();
        unset($payload['days'][2]);
        unset($payload['days'][1]['blocks'][0]['meeting_count']);

        $this->createPatterns($payload)
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        // 2 baris pola x 20 pertemuan.
        $this->assertSame(40, TeachingSchedule::count());

        $template = TeachingScheduleTemplate::where('class_id', $this->classA->id)->firstOrFail();
        $this->assertSame(20, $template->meeting_count);
        $this->assertSame(20, $template->sessions()->count());
        $this->assertSame(
            '2027-01-25',
            $template->sessions()->orderBy('session_date', 'desc')->first()->session_date->toDateString(),
        );
    }

    public function test_sessions_skip_dates_where_the_coach_is_already_booked(): void
    {
        // Coach Satu sudah punya sesi lain Senin 2026-09-14 08:30-10:00.
        TeachingSchedule::create([
            'school_id' => $this->schoolB->id,
            'class_id' => $this->classB->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-14',
            'start_time' => '08:30',
            'end_time' => '10:00',
        ]);

        $response = $this->createPatterns();
        $response->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $template = TeachingScheduleTemplate::where('class_id', $this->classA->id)->firstOrFail();

        // Pertemuan #1 (2026-09-14) bentrok -> dilewati, bukan dipaksa masuk.
        $this->assertSame(
            ['2026-09-21', '2026-09-28', '2026-10-05'],
            $template->sessions()->orderBy('session_date')->get()
                ->pluck('session_date')->map->toDateString()->all(),
        );

        // Kekurangan dilaporkan ke operator.
        $this->assertStringContainsString('Perhatian', session('success'));
        $this->assertStringContainsString('3 dari 4 pertemuan', session('success'));
    }

    // ===== Libur, generate ulang, hapus pola =====

    public function test_holiday_deletion_and_regenerate_only_fills_missing(): void
    {
        $this->createPatterns();

        $template = TeachingScheduleTemplate::where('class_id', $this->classA->id)->firstOrFail();
        $this->assertSame(4, $template->sessions()->count());

        // Libur: hapus pertemuan #2 — pola tidak tersentuh (§4).
        $second = $template->sessions()->where('meeting_number', 2)->firstOrFail();
        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.destroy', $second))
            ->assertRedirect();

        $this->assertSame(3, $template->sessions()->count());
        $this->assertSame('2026-09-14', $template->fresh()->start_date->toDateString());
        $this->assertSame(4, $template->fresh()->meeting_count);

        // Generate ulang: mengisi yang kurang, tanggal tidak bergeser.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.templates.generate', $template))
            ->assertRedirect()
            ->assertSessionHas('success');

        $sessions = $template->sessions()->orderBy('session_date')->get();
        $this->assertSame(4, $sessions->count());
        $this->assertSame(
            ['2026-09-14', '2026-09-21', '2026-09-28', '2026-10-05'],
            $sessions->pluck('session_date')->map->toDateString()->all(),
        );
        // Nomor pertemuan dirapikan ulang 1..N tanpa bolong.
        $this->assertSame([1, 2, 3, 4], $sessions->pluck('meeting_number')->all());

        // Generate ulang saat lengkap: tidak menambah apa pun.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.templates.generate', $template))
            ->assertRedirect()
            ->assertSessionHas('success', 'Tidak ada sesi baru yang perlu digenerate (semua pertemuan sudah ada).');

        $this->assertSame(4, $template->sessions()->count());
    }

    public function test_delete_pattern_row_removes_its_generated_sessions(): void
    {
        $this->createPatterns();

        $template = TeachingScheduleTemplate::where('class_id', $this->classA->id)->firstOrFail();
        $sessionIds = $template->sessions()->pluck('id')->all();
        $this->assertNotSame([], $sessionIds);

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.templates.destroy', $template))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertModelMissing($template);

        // Aturan final 2026-10-01: sesi hasil generate ikut terhapus bersama
        // baris polanya selama belum punya riwayat (laporan/absensi).
        $this->assertSame(0, TeachingSchedule::whereIn('id', $sessionIds)->count());
    }

    public function test_changing_weekly_status_does_not_destroy_or_extend_the_pattern(): void
    {
        $this->createPatterns();

        $template = TeachingScheduleTemplate::where('class_id', $this->classA->id)->firstOrFail();
        $this->assertSame(4, $template->sessions()->count());

        // §4: status mingguan ("Jalan Minggu Ini?") adalah status OPERASIONAL.
        // Mengubahnya tidak boleh mengubah pola berulang, dan generate ulang
        // setelahnya tidak boleh menambah sesi (jumlah dibatasi meeting_count).
        $this->actingAs($this->relation)
            ->put(route('admin.schedules.update', $template->sessions()->first()), [
                'school_id' => $this->schoolA->id,
                'class_id' => $this->classA->id,
                'program_id' => $this->program->id,
                'coach_id' => $this->coach->id,
                'session_date' => '2026-09-14',
                'start_time' => '08:00',
                'end_time' => '09:30',
                'jalan_minggu_ini' => '0',
            ])
            ->assertRedirect();

        $this->assertSame(4, $template->fresh()->meeting_count);
        $this->assertSame('2026-09-14', $template->fresh()->start_date->toDateString());

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.pattern.generate'), [
                'day' => 1,
                'school_id' => $this->schoolA->id,
                'start_date' => '2026-09-14',
            ])
            ->assertRedirect();

        // Tetap 8 sesi (2 kelas x 4 pertemuan) — tidak ada yang bertambah.
        $this->assertSame(8, TeachingSchedule::where('school_id', $this->schoolA->id)->count());
    }

    // ===== Scope & permission =====

    public function test_pic_cannot_manage_patterns_outside_scope(): void
    {
        $this->createPatterns();

        // Baris pola sekolah B (di luar plot PIC A).
        $template = TeachingScheduleTemplate::where('school_id', $this->schoolB->id)->firstOrFail();

        $this->actingAs($this->picA)
            ->post(route('admin.schedules.templates.generate', $template))
            ->assertForbidden();
        $this->actingAs($this->picA)
            ->delete(route('admin.schedules.templates.destroy', $template))
            ->assertForbidden();
        $this->actingAs($this->picA)
            ->post(route('admin.schedules.pattern.generate'), [
                'day' => 2,
                'school_id' => $this->schoolB->id,
                'start_date' => '2026-09-15',
            ])
            ->assertForbidden();

        $this->assertModelExists($template);
        $this->assertSame(4, $template->sessions()->count());
    }

    public function test_coach_and_finance_cannot_access_pattern_management(): void
    {
        $this->actingAs($this->coach)
            ->get(route('admin.schedules.semester'))
            ->assertForbidden();
        $this->actingAs($this->coach)
            ->get(route('admin.schedules.templates'))
            ->assertForbidden();

        $this->actingAs($this->finance)
            ->get(route('admin.schedules.templates'))
            ->assertForbidden();
        $this->actingAs($this->finance)
            ->get(route('admin.schedules.create'))
            ->assertForbidden();
    }

    public function test_templates_page_lists_patterns_scoped_per_role(): void
    {
        $this->createPatterns();

        $this->actingAs($this->relation)
            ->get(route('admin.schedules.templates'))
            ->assertOk()
            ->assertSee($this->schoolA->name)
            ->assertSee($this->schoolB->name)
            ->assertSee('4 pertemuan');

        // Filter hari: hanya pola SENIN yang tampil.
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.templates', ['day' => 1]))
            ->assertOk()
            ->assertSee($this->schoolA->name)
            ->assertDontSee($this->schoolB->name);

        // PIC A hanya melihat pola sekolah plot-nya.
        $this->actingAs($this->picA)
            ->get(route('admin.schedules.templates'))
            ->assertOk()
            ->assertSee($this->schoolA->name)
            ->assertDontSee($this->schoolB->name);
    }

    // ===== Visibility & reminder =====

    public function test_coaches_see_generated_sessions(): void
    {
        $this->createPatterns();

        // Coach utama.
        $this->actingAs($this->coach)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee($this->schoolA->name)
            ->assertSee('14 Sep 2026');

        // Coach tambahan tetap melihat sesi hasil generate.
        $this->actingAs($this->coachExtra)
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee($this->schoolA->name);

        // Coach tidak melihat sesi sekolah yang tidak melibatkannya. Daftar
        // default adalah SENIN (§5), jadi pola Selasa dibuka lewat tab hari.
        $this->actingAs($this->coachB)
            ->get(route('admin.schedules.index', ['day' => 2]))
            ->assertOk()
            ->assertSee($this->schoolB->name)
            ->assertDontSee($this->schoolA->name);
    }

    public function test_reminder_follows_generated_session_dates_of_each_school(): void
    {
        // Dua sekolah pada hari SENIN dengan tanggal mulai BERBEDA, keduanya di
        // masa lalu. Reminder harus menghitung dari tanggal sesi masing-masing.
        // - Sekolah A: mulai 2026-08-03 (Senin) -> 03 & 10 Agustus.
        // - Sekolah B: mulai 2026-08-17 (Senin) -> 17 & 24 Agustus.
        $payload = ['days' => [
            1 => ['blocks' => [
                [
                    'school_id' => $this->schoolA->id,
                    'start_date' => '2026-08-03',
                    'meeting_count' => 2,
                    'rows' => [[
                        'class_id' => $this->classA->id,
                        'coach_id' => $this->coach->id,
                        'start_time' => '08:00',
                        'end_time' => '09:00',
                    ]],
                ],
                [
                    'school_id' => $this->schoolB->id,
                    'start_date' => '2026-08-17',
                    'meeting_count' => 2,
                    'rows' => [[
                        'class_id' => $this->classB->id,
                        'coach_id' => $this->coachB->id,
                        'start_time' => '10:00',
                        'end_time' => '11:00',
                    ]],
                ],
            ]],
        ]];

        $this->createPatterns($payload)
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'pola']);

        $this->assertSame(
            ['2026-08-03', '2026-08-10'],
            TeachingSchedule::where('coach_id', $this->coach->id)
                ->orderBy('session_date')->get()
                ->pluck('session_date')->map->toDateString()->all(),
        );
        $this->assertSame(
            ['2026-08-17', '2026-08-24'],
            TeachingSchedule::where('coach_id', $this->coachB->id)
                ->orderBy('session_date')->get()
                ->pluck('session_date')->map->toDateString()->all(),
        );

        $this->actingAs($this->relation)
            ->post(route('admin.reports.remind'))
            ->assertRedirect();

        // Tiap coach dinilai dari sesi pola sekolahnya sendiri.
        $this->assertSame(1, $this->coach->notifications()->count());
        $this->assertSame(2, $this->coach->notifications->first()->data['missing_count']);
        $this->assertSame(1, $this->coachB->notifications()->count());
        $this->assertSame(2, $this->coachB->notifications->first()->data['missing_count']);
    }

    public function test_reminder_ignores_generated_session_with_matching_report(): void
    {
        $payload = ['days' => [
            1 => ['blocks' => [[
                'school_id' => $this->schoolA->id,
                'start_date' => '2026-08-03',
                'meeting_count' => 2,
                'rows' => [[
                    'class_id' => $this->classA->id,
                    'coach_id' => $this->coach->id,
                    'start_time' => '08:00',
                    'end_time' => '09:00',
                ]],
            ]]],
        ]];

        $this->createPatterns($payload);

        // Laporan untuk pertemuan #1 sudah masuk.
        Report::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'report_date' => '2026-08-03',
            'lesson_material' => 'Materi',
            'activity_summary' => 'Ringkasan',
            'status' => 'submitted',
        ]);

        $this->actingAs($this->relation)
            ->post(route('admin.reports.remind'))
            ->assertRedirect();

        // Hanya pertemuan #2 (2026-08-10) yang masih kurang.
        $this->assertSame(1, $this->coach->notifications()->count());
        $this->assertSame(1, $this->coach->notifications->first()->data['missing_count']);
    }
}
