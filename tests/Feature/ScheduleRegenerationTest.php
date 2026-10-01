<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Aturan final 2026-09-28 — REGENERASI TIDAK MENIMPA PERUBAHAN MANUAL.
 *
 * Perubahan coach pada sesi yang SUDAH ada (mis. coach utama diganti, coach
 * pendamping dicopot karena sakit) adalah keputusan operasional admin. Generate
 * ulang hanya boleh MENAMBAH sesi yang belum ada; sesi lama tidak pernah
 * ditulis ulang, dan coach yang sengaja dicopot tidak boleh kembali sendiri.
 *
 * Perubahan template berlaku untuk sesi BARU — dan bila kelak dibutuhkan
 * "Sync Template ke Sesi Lama", itu harus aksi eksplisit, bukan efek samping
 * generate. Tes di bawah mengunci sifat itu.
 */
class ScheduleRegenerationTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private SchoolClass $class;
    private Program $program;
    private User $coachA;
    private User $coachB;
    private User $coachC;
    private User $relation;

    /** Senin acuan. */
    private const START = '2026-01-05';

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SD Regenerasi']);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 3A']);
        $this->program = Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);

        $this->coachA = $this->makeUser(User::ROLE_COACH, 'coach.a');
        $this->coachB = $this->makeUser(User::ROLE_COACH, 'coach.b');
        $this->coachC = $this->makeUser(User::ROLE_COACH, 'coach.c');

        // Coach A & B ter-assign permanen (agar sah menjadi coach utama sesi
        // mana pun di kelas ini). Coach C hanya coach pendamping.
        foreach ([$this->coachA, $this->coachB] as $coach) {
            CoachClass::create(['coach_id' => $coach->id, 'class_id' => $this->class->id]);
        }

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
     * Pola Senin lewat form bulk resmi: template + sesi hasil generate.
     */
    private function buildPattern(int $meetings, array $additionalCoaches = []): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), [
                'days' => [
                    1 => [
                        'blocks' => [[
                            'school_id' => $this->school->id,
                            'start_date' => self::START,
                            'meeting_count' => $meetings,
                            'rows' => [[
                                'class_id' => $this->class->id,
                                'program_id' => $this->program->id,
                                'coach_id' => $this->coachA->id,
                                'additional_coaches' => array_map(fn (User $u) => $u->id, $additionalCoaches),
                                'start_time' => '08:00',
                                'end_time' => '09:30',
                                'student_count' => 10,
                            ]],
                        ]],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();
    }

    private function regenerate(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.pattern.generate'), [
                'day' => 1,
                'school_id' => $this->school->id,
                'start_date' => self::START,
            ])
            ->assertSessionHasNoErrors();
    }

    private function sessionAt(int $meetingNumber): TeachingSchedule
    {
        return TeachingSchedule::where('class_id', $this->class->id)
            ->where('meeting_number', $meetingNumber)
            ->firstOrFail();
    }

    private function template(): TeachingScheduleTemplate
    {
        return TeachingScheduleTemplate::where('class_id', $this->class->id)->firstOrFail();
    }

    public function test_regeneration_does_not_restore_a_coach_removed_from_an_existing_session(): void
    {
        $this->buildPattern(4, [$this->coachC]);

        $session = $this->sessionAt(1);
        $this->assertSame($this->coachA->id, (int) $session->coach_id);
        $this->assertTrue($session->additionalCoaches()->whereKey($this->coachC->id)->exists());

        // Perubahan manual: coach utama diganti, coach pendamping dicopot.
        $this->actingAs($this->relation)
            ->put(route('admin.schedules.update', $session), [
                'school_id' => $this->school->id,
                'class_id' => $this->class->id,
                'program_id' => $this->program->id,
                'coach_id' => $this->coachB->id,
                'additional_coaches' => [],
                'session_date' => $session->session_date->toDateString(),
                'start_time' => '08:00',
                'end_time' => '09:30',
            ])
            ->assertSessionHasNoErrors();

        // Template minta pertemuan lebih banyak → generate ulang menambah sesi.
        $this->template()->update(['meeting_count' => 6]);
        $this->regenerate();

        $this->assertSame(6, TeachingSchedule::where('class_id', $this->class->id)->count());

        // Sesi lama tidak ditulis ulang: coach pengganti tetap, coach yang
        // dicopot tidak kembali.
        $session->refresh();
        $this->assertSame($this->coachB->id, (int) $session->coach_id);
        $this->assertFalse($session->additionalCoaches()->whereKey($this->coachC->id)->exists());

        // Sesi BARU memakai coach dari template — perubahan template memang
        // berlaku untuk sesi yang baru dibuat.
        $new = $this->sessionAt(6);
        $this->assertSame($this->coachA->id, (int) $new->coach_id);
        $this->assertTrue($new->additionalCoaches()->whereKey($this->coachC->id)->exists());
    }

    public function test_regeneration_with_nothing_to_add_leaves_existing_sessions_alone(): void
    {
        $this->buildPattern(3);

        $session = $this->sessionAt(1);

        $this->actingAs($this->relation)
            ->put(route('admin.schedules.update', $session), [
                'school_id' => $this->school->id,
                'class_id' => $this->class->id,
                'program_id' => $this->program->id,
                'coach_id' => $this->coachB->id,
                'additional_coaches' => [],
                'session_date' => $session->session_date->toDateString(),
                'start_time' => '08:00',
                'end_time' => '09:30',
            ])
            ->assertSessionHasNoErrors();

        $before = TeachingSchedule::where('class_id', $this->class->id)
            ->orderBy('meeting_number')
            ->get()
            ->map(fn (TeachingSchedule $s) => [$s->id, (int) $s->coach_id, $s->session_date->toDateString()])
            ->all();

        $this->regenerate();

        $after = TeachingSchedule::where('class_id', $this->class->id)
            ->orderBy('meeting_number')
            ->get()
            ->map(fn (TeachingSchedule $s) => [$s->id, (int) $s->coach_id, $s->session_date->toDateString()])
            ->all();

        $this->assertSame($before, $after, 'Generate ulang tidak boleh mengubah sesi yang sudah ada.');
    }

    public function test_newly_generated_sessions_still_receive_template_coaches(): void
    {
        $this->buildPattern(2, [$this->coachC]);

        $this->template()->update(['meeting_count' => 3]);
        $this->regenerate();

        $new = $this->sessionAt(3);

        $this->assertSame($this->coachA->id, (int) $new->coach_id);
        $this->assertTrue($new->additionalCoaches()->whereKey($this->coachC->id)->exists());
        $this->assertSame(3, (int) $new->meeting_number);
    }
}
