<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Meeting 2026-09-27 — coach pendamping pada satu sesi.
 *
 * Dua aturan bisnis:
 *
 * 1. VISIBILITAS BERSAMA. Coach utama dan coach pendamping pada SESI yang sama
 *    melihat sekolah, kelas, tanggal, jam, dan nama satu sama lain. Yang tidak
 *    terlibat pada sesi itu tidak melihat apa pun.
 *
 * 2. PENUGASAN SEMENTARA. Coach pendamping boleh dipilih walau belum punya
 *    assignment permanen (`coach_classes`) pada sekolah/kelas tersebut. Akses
 *    itu hidup di pivot `teaching_schedule_coach` pada sesi terkait saja:
 *    tidak membuat baris coach_classes, tidak memberi akses ke sekolah secara
 *    global, dan hilang begitu coach dicopot dari jadwal — tanpa menghapus
 *    laporan historis miliknya.
 */
class SharedCoachAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private Program $program;
    private User $coachPrimary;
    private User $coachTemp;
    private User $coachOutsider;
    private User $relation;

    private const SESSION_DATE = '2026-03-02';

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Syafana']);
        $this->schoolB = School::create(['name' => 'SD Lain']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 4A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 5B']);

        $this->program = Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);

        // Coach utama: ter-assign permanen ke kelas A.
        $this->coachPrimary = $this->makeUser(User::ROLE_COACH, 'coach.wildan');
        CoachClass::create(['coach_id' => $this->coachPrimary->id, 'class_id' => $this->classA->id]);

        // Coach pendamping: TIDAK pernah punya assignment ke kelas A.
        $this->coachTemp = $this->makeUser(User::ROLE_COACH, 'coach.renaldy');
        CoachClass::create(['coach_id' => $this->coachTemp->id, 'class_id' => $this->classB->id]);

        // Coach lain: hanya di sekolah lain, tidak terlibat sesi mana pun di A.
        $this->coachOutsider = $this->makeUser(User::ROLE_COACH, 'coach.outsider');
        CoachClass::create(['coach_id' => $this->coachOutsider->id, 'class_id' => $this->classB->id]);

        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation');
    }

    private function makeUser(string $role, string $slug): User
    {
        return User::create([
            'name' => ucwords(str_replace('.', ' ', $slug)),
            'email' => $slug.'@test.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    /**
     * Sesi kelas A: coach utama + coach pendamping sementara (lewat form CRUD,
     * jalur yang sama dengan yang dipakai admin di UI).
     */
    private function sharedSession(array $overrides = []): TeachingSchedule
    {
        $payload = array_merge([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'program_id' => $this->program->id,
            'coach_id' => $this->coachPrimary->id,
            'additional_coaches' => [$this->coachTemp->id],
            'session_date' => self::SESSION_DATE,
            'start_time' => '08:00',
            'end_time' => '09:30',
            'student_count' => 12,
        ], $overrides);

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $payload)
            ->assertSessionHasNoErrors();

        return TeachingSchedule::where('class_id', $this->classA->id)
            ->whereDate('session_date', self::SESSION_DATE)
            ->firstOrFail();
    }

    private function student(): Student
    {
        return Student::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'name' => 'Murid Satu',
        ]);
    }

    // ===== 1. Visibilitas bersama =====

    public function test_primary_and_additional_coach_see_the_same_session_and_each_other(): void
    {
        $this->sharedSession();

        foreach ([$this->coachPrimary, $this->coachTemp] as $coach) {
            $response = $this->actingAs($coach)
                ->get(route('admin.schedules.index', ['view' => 'sesi']))
                ->assertOk();

            // Sekolah, kelas, tanggal, jam.
            $response->assertSee('SD Syafana');
            $response->assertSee('Grade 4A');
            $response->assertSee('02 Mar 2026');
            $response->assertSee('08:00');
            $response->assertSee('09:30');

            // Kedua coach saling melihat, dengan perannya masing-masing.
            $response->assertSee($this->coachPrimary->name);
            $response->assertSee($this->coachTemp->name);
            $response->assertSee('Coach Utama');
            $response->assertSee('Coach Pendamping');
        }
    }

    public function test_unrelated_coach_does_not_see_the_shared_session(): void
    {
        $this->sharedSession();

        $this->actingAs($this->coachOutsider)
            ->get(route('admin.schedules.index', ['view' => 'sesi']))
            ->assertOk()
            ->assertDontSee('SD Syafana')
            ->assertDontSee('Grade 4A')
            ->assertDontSee($this->coachPrimary->name)
            ->assertDontSee($this->coachTemp->name);
    }

    public function test_coach_filter_lists_the_shared_session_for_both_coaches(): void
    {
        $session = $this->sharedSession();

        // Tanggal sesi dipakai sebagai penanda baris: nama kelas juga muncul di
        // dropdown filter, jadi tidak bisa dipakai untuk membuktikan isi tabel.
        $rowMarker = $session->session_date->format('d M Y');

        foreach ([$this->coachPrimary, $this->coachTemp] as $coach) {
            $this->actingAs($this->relation)
                ->get(route('admin.schedules.index', [
                    'view' => 'sesi',
                    'coach_id' => $coach->id,
                ]))
                ->assertOk()
                ->assertSee($rowMarker);
        }

        // Coach di luar sesi tidak muncul pada hasil filter.
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', [
                'view' => 'sesi',
                'coach_id' => $this->coachOutsider->id,
            ]))
            ->assertOk()
            ->assertDontSee($rowMarker);

        $this->assertTrue(
            $session->additionalCoaches()->whereKey($this->coachTemp->id)->exists()
        );
    }

    // ===== 2. Penugasan sementara =====

    public function test_additional_coach_can_be_assigned_without_permanent_class_assignment(): void
    {
        $this->sharedSession();

        // Tidak ada baris coach_classes baru untuk kelas A…
        $this->assertFalse(
            CoachClass::where('coach_id', $this->coachTemp->id)
                ->where('class_id', $this->classA->id)
                ->exists(),
            'Penugasan sementara tidak boleh mengubah assignment permanen.'
        );

        // …tetapi sesi mencatatnya sebagai coach pendamping.
        $session = TeachingSchedule::where('class_id', $this->classA->id)->firstOrFail();
        $this->assertTrue($session->additionalCoaches()->whereKey($this->coachTemp->id)->exists());
    }

    public function test_temporary_coach_can_submit_report_for_the_scheduled_date(): void
    {
        $this->sharedSession();
        $student = $this->student();

        $this->actingAs($this->coachTemp)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => self::SESSION_DATE,
                'lesson_material' => 'Materi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$student->id => 'present'],
            ])
            ->assertRedirect();

        $this->assertTrue(
            Report::where('coach_id', $this->coachTemp->id)
                ->where('class_id', $this->classA->id)
                ->whereDate('report_date', self::SESSION_DATE)
                ->exists()
        );
    }

    public function test_temporary_access_is_limited_to_the_scheduled_date(): void
    {
        $this->sharedSession();
        $student = $this->student();

        // Tanggal lain pada kelas yang sama bukan bagian dari penugasan
        // sementara → tidak ada izin menulis.
        $this->actingAs($this->coachTemp)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => '2026-03-09',
                'lesson_material' => 'Materi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$student->id => 'present'],
            ])
            ->assertForbidden();
    }

    public function test_coach_without_any_link_cannot_report_on_the_class(): void
    {
        $this->sharedSession();
        $student = $this->student();

        $this->actingAs($this->coachOutsider)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => self::SESSION_DATE,
                'lesson_material' => 'Materi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$student->id => 'present'],
            ])
            ->assertForbidden();
    }

    public function test_temporary_coach_sees_the_class_in_the_report_form(): void
    {
        $this->sharedSession();

        $this->actingAs($this->coachTemp)
            ->get(route('coach.reports.create'))
            ->assertOk()
            ->assertSee('Grade 4A');

        $this->actingAs($this->coachOutsider)
            ->get(route('coach.reports.create'))
            ->assertOk()
            ->assertDontSee('Grade 4A');
    }

    public function test_temporary_access_does_not_grant_global_school_access(): void
    {
        $this->sharedSession();

        // Kelas lain di sekolah yang sama tetap tertutup.
        $otherClass = SchoolClass::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Grade 6Z',
        ]);
        $student = Student::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $otherClass->id,
            'name' => 'Murid Lain',
        ]);

        $this->actingAs($this->coachTemp)
            ->post(route('coach.reports.store'), [
                'class_id' => $otherClass->id,
                'report_date' => self::SESSION_DATE,
                'lesson_material' => 'Materi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$student->id => 'present'],
            ])
            ->assertForbidden();

        $this->actingAs($this->coachTemp)
            ->get(route('coach.reports.create'))
            ->assertOk()
            ->assertDontSee('Grade 6Z');
    }

    // ===== 3. Pencabutan penugasan sementara =====

    public function test_removing_temporary_coach_revokes_access_but_keeps_history(): void
    {
        $session = $this->sharedSession();
        $student = $this->student();

        $this->actingAs($this->coachTemp)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => self::SESSION_DATE,
                'lesson_material' => 'Materi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$student->id => 'present'],
            ])
            ->assertRedirect();

        // Copot coach pendamping dari jadwal.
        $this->actingAs($this->relation)
            ->put(route('admin.schedules.update', $session), [
                'school_id' => $this->schoolA->id,
                'class_id' => $this->classA->id,
                'program_id' => $this->program->id,
                'coach_id' => $this->coachPrimary->id,
                'additional_coaches' => [],
                'session_date' => self::SESSION_DATE,
                'start_time' => '08:00',
                'end_time' => '09:30',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(
            $session->refresh()->additionalCoaches()->whereKey($this->coachTemp->id)->exists()
        );

        // Akses menulis pada tanggal itu dicabut…
        $this->actingAs($this->coachTemp)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => '2026-03-16',
                'lesson_material' => 'Materi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$student->id => 'present'],
            ])
            ->assertForbidden();

        // …tetapi laporan historisnya tetap ada dan tetap miliknya.
        $this->assertTrue(
            Report::where('coach_id', $this->coachTemp->id)
                ->where('class_id', $this->classA->id)
                ->whereDate('report_date', self::SESSION_DATE)
                ->exists(),
            'Mencabut penugasan sementara tidak boleh menghapus laporan historis.'
        );

        $this->actingAs($this->coachTemp)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Grade 4A');
    }

    // ===== 4. Form bulk menyediakan coach yang belum ter-assign =====

    public function test_bulk_form_offers_unassigned_coach_as_additional_coach(): void
    {
        // coach.temp tidak punya coach_classes untuk kelas A, tetapi harus
        // tetap bisa dipilih sebagai coach pendamping.
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.create'))
            ->assertOk()
            ->assertSee($this->coachTemp->name);
    }

    public function test_bulk_store_accepts_unassigned_additional_coach(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), [
                'days' => [
                    1 => [
                        'blocks' => [[
                            'school_id' => $this->schoolA->id,
                            'start_date' => '2026-03-02',
                            'meeting_count' => 2,
                            'rows' => [[
                                'class_id' => $this->classA->id,
                                'program_id' => $this->program->id,
                                'coach_id' => $this->coachPrimary->id,
                                'additional_coaches' => [$this->coachTemp->id],
                                'start_time' => '10:00',
                                'end_time' => '11:30',
                            ]],
                        ]],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, TeachingSchedule::where('class_id', $this->classA->id)->count());
        $this->assertFalse(
            CoachClass::where('coach_id', $this->coachTemp->id)
                ->where('class_id', $this->classA->id)
                ->exists()
        );
    }

    public function test_bulk_store_still_requires_permanent_assignment_for_primary_coach(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), [
                'days' => [
                    1 => [
                        'blocks' => [[
                            'school_id' => $this->schoolA->id,
                            'start_date' => '2026-03-02',
                            'meeting_count' => 2,
                            'rows' => [[
                                'class_id' => $this->classA->id,
                                'program_id' => $this->program->id,
                                'coach_id' => $this->coachTemp->id, // bukan coach kelas A
                                'start_time' => '10:00',
                                'end_time' => '11:30',
                            ]],
                        ]],
                    ],
                ],
            ])
            ->assertSessionHasErrors('schedules');

        $this->assertSame(0, TeachingSchedule::where('class_id', $this->classA->id)->count());
    }
}
