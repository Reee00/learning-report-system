<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\ProgramClass;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceScopeService;
use App\Services\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Menjaga DatabaseSeeder tetap sinkron dengan sumber datanya, sheet
 * "🏫 DIGISchool - SENIN" pada "_📅 Sistem Academic - CENTER & DIGISchool.xlsx".
 *
 * Seeder adalah satu-satunya jalur pengisian data development, jadi angka di
 * bawah adalah kontrak: kalau sheet berubah, test ini gagal dan memaksa
 * keputusan sadar, bukan drift diam-diam.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    // 8 akun role + 27 akun coach (COACH_NAMES) tanpa penugasan.
    private const EXPECTED_USERS = 35;
    private const EXPECTED_SCHOOLS = 13;
    private const EXPECTED_PROGRAMS = 8;
    private const EXPECTED_CLASSES = 21;
    private const EXPECTED_PROGRAM_CLASSES = 22;
    private const EXPECTED_COACH_CLASSES = 7;
    private const EXPECTED_STUDENTS = 30;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_it_seeds_the_expected_master_data_counts(): void
    {
        $this->assertSame(self::EXPECTED_USERS, User::count());
        $this->assertSame(self::EXPECTED_SCHOOLS, School::count());
        $this->assertSame(self::EXPECTED_PROGRAMS, Program::count());
        $this->assertSame(self::EXPECTED_CLASSES, SchoolClass::count());
        $this->assertSame(self::EXPECTED_PROGRAM_CLASSES, ProgramClass::count());
        $this->assertSame(self::EXPECTED_COACH_CLASSES, CoachClass::count());
        $this->assertSame(self::EXPECTED_STUDENTS, Student::count());
    }

    public function test_running_the_seeder_twice_does_not_create_duplicates(): void
    {
        $this->seed();

        $this->assertSame(self::EXPECTED_USERS, User::count());
        $this->assertSame(self::EXPECTED_SCHOOLS, School::count());
        $this->assertSame(self::EXPECTED_PROGRAMS, Program::count());
        $this->assertSame(self::EXPECTED_CLASSES, SchoolClass::count());
        $this->assertSame(self::EXPECTED_PROGRAM_CLASSES, ProgramClass::count());
        $this->assertSame(self::EXPECTED_COACH_CLASSES, CoachClass::count());
        $this->assertSame(self::EXPECTED_STUDENTS, Student::count());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function roleLandingPageProvider(): array
    {
        return [
            'superadmin'     => ['superadmin@lrs.com', 'admin.dashboard'],
            'relation'       => ['admin@lrs.com', 'admin.dashboard'],
            'spv coach'      => ['spv@lrs.com', 'admin.coaches.index'],
            'coach'          => ['coach@lrs.com', 'coach.reports.index'],
            'school pic'     => ['pic@lrs.com', 'pic.dashboard'],
            'teacher school' => ['teacher@lrs.com', 'attendance.index'],
            'finance'        => ['finance@lrs.com', 'attendance.index'],
        ];
    }

    #[DataProvider('roleLandingPageProvider')]
    public function test_every_role_account_can_authenticate(string $email, string $route): void
    {
        $this->post('/login', ['email' => $email, 'password' => 'password'])
            ->assertRedirectToRoute($route);

        $this->assertAuthenticatedAs(User::where('email', $email)->firstOrFail());
    }

    public function test_relation_and_superadmin_have_global_school_scope(): void
    {
        $authorization = app(AuthorizationService::class);

        foreach (['admin@lrs.com', 'superadmin@lrs.com'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertNull(
                $authorization->accessibleSchoolIds($user),
                "{$email} seharusnya punya scope global."
            );
        }
    }

    public function test_school_pic_is_limited_to_its_single_plotted_school(): void
    {
        $pic = User::where('email', 'pic@lrs.com')->firstOrFail();
        $schoolIds = app(AuthorizationService::class)->accessibleSchoolIds($pic);

        $this->assertSame([School::where('name', 'PENABUR MODERNLAND')->value('id')], $schoolIds);
    }

    public function test_teacher_school_is_limited_to_its_single_plotted_school(): void
    {
        $teacher = User::where('email', 'teacher@lrs.com')->firstOrFail();
        $schoolIds = app(AuthorizationService::class)->accessibleSchoolIds($teacher);

        $this->assertSame([School::where('name', 'PENABUR GS')->value('id')], $schoolIds);
    }

    /**
     * Requirement bisnis final: Finance punya visibilitas attendance
     * all-school. Scope-nya datang dari role, bukan dari plot sekolah —
     * karena itu Finance tidak di-plot sama sekali oleh seeder.
     */
    public function test_finance_has_all_school_scope_from_its_role_not_from_plotting(): void
    {
        $finance = User::where('email', 'finance@lrs.com')->firstOrFail();

        $this->assertNull(
            app(AuthorizationService::class)->accessibleSchoolIds($finance),
            'Finance harus global; kalau tidak, filter sekolah akan menyempitkannya.'
        );
        $this->assertSame([], $finance->assignedSchoolIds(), 'Finance tidak boleh bergantung pada plot sekolah.');
    }

    public function test_finance_reaches_every_seeded_school_through_the_scope_service(): void
    {
        $finance = User::where('email', 'finance@lrs.com')->firstOrFail();

        $this->assertCount(self::EXPECTED_SCHOOLS, app(AttendanceScopeService::class)->schoolsFor($finance));
    }

    public function test_coach_scope_is_exactly_its_class_assignments(): void
    {
        $coach = User::where('email', 'coach@lrs.com')->firstOrFail();
        $authorization = app(AuthorizationService::class);

        $assignedClasses = SchoolClass::whereIn(
            'id',
            CoachClass::where('coach_id', $coach->id)->pluck('class_id')
        )->get();

        $this->assertCount(6, $assignedClasses);

        foreach ($assignedClasses as $class) {
            $this->assertTrue($authorization->canAccessClass($coach, $class));
        }

        $notAssigned = SchoolClass::whereNotIn('id', $assignedClasses->pluck('id'))->firstOrFail();
        $this->assertFalse($authorization->canAccessClass($coach, $notAssigned));
    }

    public function test_multi_coach_assignment_puts_two_coaches_on_one_class(): void
    {
        $classId = SchoolClass::where('school_id', School::where('name', 'PENABUR MODERNLAND')->value('id'))
            ->where('name', 'SD 3')
            ->value('id');

        $coachIds = CoachClass::where('class_id', $classId)->pluck('coach_id')->all();

        $this->assertCount(2, $coachIds);
        $this->assertEqualsCanonicalizing(
            User::whereIn('email', ['coach@lrs.com', 'coach2@lrs.com'])->pluck('id')->all(),
            $coachIds
        );
    }

    /**
     * Kelas bersifat per-sekolah: label yang sama di dua sekolah harus menjadi
     * dua baris classes yang berbeda, bukan satu baris bersama.
     */
    public function test_the_same_class_label_stays_school_specific(): void
    {
        $modernland = School::where('name', 'PENABUR MODERNLAND')->value('id');
        $gs = School::where('name', 'PENABUR GS')->value('id');

        $this->assertNotSame(
            SchoolClass::where('school_id', $modernland)->where('name', 'SD 3')->value('id'),
            SchoolClass::where('school_id', $gs)->where('name', 'SD 3')->value('id')
        );
    }

    public function test_every_seeded_class_belongs_to_its_own_school(): void
    {
        $this->assertSame(0, SchoolClass::whereNotIn('school_id', School::pluck('id'))->count());
        $this->assertSame(0, SchoolClass::whereNull('school_id')->count());
    }

    /**
     * Hanya program yang benar-benar ada di sheet yang boleh dibuat. "AI"
     * terlihat di blok EST ALFA INDAH tetapi tidak termasuk daftar program
     * yang diminta, jadi sengaja tidak di-seed.
     */
    public function test_only_the_eight_sheet_programs_exist(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Coding', 'DK', 'Graphic Design', 'STEM', 'Content Creator', 'Robot', 'Art Factory', 'Robotic'],
            Program::pluck('name')->all()
        );
    }

    /**
     * EST ALFA INDAH "SD 1 - SMP" adalah satu-satunya kelas dengan dua program
     * pada sheet, sehingga total pivot program_classes (22) melebihi jumlah
     * kelas (21).
     */
    public function test_est_alfa_indah_shared_class_carries_two_programs(): void
    {
        $classId = SchoolClass::where('school_id', School::where('name', 'EST ALFA INDAH')->value('id'))
            ->where('name', 'SD 1 - SMP')
            ->value('id');

        $programs = Program::whereIn(
            'id',
            ProgramClass::where('class_id', $classId)->pluck('program_id')
        )->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['STEM', 'Content Creator'], $programs);
    }

    public function test_every_coach_assigned_class_has_a_student_roster(): void
    {
        foreach (CoachClass::distinct()->pluck('class_id') as $classId) {
            $this->assertGreaterThan(
                0,
                Student::where('class_id', $classId)->count(),
                "Kelas {$classId} tidak punya siswa, alur absensi tidak bisa diuji."
            );
        }
    }
}
