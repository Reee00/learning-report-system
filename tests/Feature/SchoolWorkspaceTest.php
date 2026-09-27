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
 * School Workspace (2026-09-24):
 *
 * Halaman detail sekolah menjadi satu tempat untuk setup sekolah —
 * informasi sekolah, kelas yang ter-assign, dan program per kelas.
 *
 * Prinsip yang diuji di sini:
 * - Kelas tetap master data yang sama (App\Models\SchoolClass +
 *   pivot program_classes) — tidak ada tabel kelas kedua.
 * - Assign kelas yang sudah ada = memindahkan kelas master, dan hanya
 *   kelas kosong yang boleh dipindah agar school_id pada laporan/jadwal
 *   historis tidak pernah bertentangan dengan school_id kelasnya.
 * - Duplikat kelas pada satu sekolah ditolak (perbandingan tidak peka
 *   huruf besar/kecil).
 * - Scope: PIC/Teacher tidak punya schools.* maupun program_classes.*,
 *   Coach tidak punya program_classes.*, sehingga manipulasi URL/payload
 *   tetap terlindungi.
 */
class SchoolWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private Program $coding;
    private Program $robotics;
    private User $relation;
    private User $superadmin;
    private User $picA;
    private User $coach;
    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Workspace A']);
        $this->schoolB = School::create(['name' => 'SD Workspace B']);

        $this->coding = Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);
        $this->robotics = Program::create(['name' => 'Robotics', 'code' => 'ROB', 'status' => 'active']);

        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation');
        $this->superadmin = $this->makeUser(User::ROLE_SUPERADMIN, 'superadmin');
        $this->coach = $this->makeUser(User::ROLE_COACH, 'coach');
        $this->finance = $this->makeUser(User::ROLE_FINANCE, 'finance');

        $this->picA = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.a');
        $this->picA->schools()->attach($this->schoolA->id);
    }

    // ===== Tampilan workspace =====

    public function test_school_detail_shows_information_classes_and_programs(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);
        $class->programs()->attach([$this->coding->id, $this->robotics->id]);

        $response = $this->actingAs($this->relation)
            ->get(route('admin.schools.show', $this->schoolA))
            ->assertOk();

        // Tiga bagian workspace terlihat.
        $response->assertSee('Informasi Sekolah');
        $response->assertSee('Kelas di Sekolah Ini');
        $response->assertSee('Tambah / Assign Kelas');

        // Kelas dan program yang ter-assign langsung tampak tanpa pindah halaman.
        $response->assertSee('Grade 5A');
        $response->assertSee('Coding');
        $response->assertSee('Robotics');

        // Halaman menautkan kelas ke Master Class (sumber data yang sama).
        $response->assertSee(route('admin.classes.index'), false);
    }

    public function test_workspace_only_lists_classes_of_this_school(): void
    {
        SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas Sekolah A']);

        // Kelas sekolah B yang masih terpakai (punya murid) tidak boleh
        // muncul sebagai kandidat assign — hanya kelas kosong yang bisa
        // dipindahkan ke sekolah ini.
        $usedClassB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas Sekolah B']);
        Student::create(['class_id' => $usedClassB->id, 'name' => 'Murid B']);

        $this->actingAs($this->relation)
            ->get(route('admin.schools.show', $this->schoolA))
            ->assertOk()
            ->assertSee('Kelas Sekolah A')
            ->assertDontSee('Kelas Sekolah B');
    }

    public function test_workspace_offers_empty_classes_from_other_schools_as_candidates(): void
    {
        $empty = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas Kosong B']);

        $this->actingAs($this->relation)
            ->get(route('admin.schools.show', $this->schoolA))
            ->assertOk()
            ->assertSee('Kelas Kosong B')
            ->assertSee((string) $empty->id);
    }

    // ===== A. Assign kelas =====

    public function test_create_new_class_from_workspace_with_programs(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schools.classes.store', $this->schoolA), [
                'mode' => 'new',
                'name' => 'Grade 6B',
                'program_ids' => [$this->coding->id],
            ])
            ->assertRedirectToRoute('admin.schools.show', $this->schoolA)
            ->assertSessionHas('success');

        $class = SchoolClass::where('name', 'Grade 6B')->firstOrFail();
        $this->assertSame($this->schoolA->id, $class->school_id);
        $this->assertSame([$this->coding->id], $class->programs->pluck('id')->all());
    }

    public function test_existing_class_can_be_assigned_by_moving_it(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 4C']);

        $this->actingAs($this->relation)
            ->post(route('admin.schools.classes.store', $this->schoolA), [
                'mode' => 'existing',
                'class_id' => $class->id,
                'program_ids' => [$this->robotics->id],
            ])
            ->assertRedirectToRoute('admin.schools.show', $this->schoolA)
            ->assertSessionHas('success');

        $class->refresh();
        $this->assertSame($this->schoolA->id, $class->school_id);
        $this->assertSame([$this->robotics->id], $class->programs->pluck('id')->all());

        // Tidak ada kelas duplikat: tetap satu baris dengan id yang sama.
        $this->assertSame(1, SchoolClass::where('name', 'Grade 4C')->count());
    }

    public function test_assigning_class_already_in_this_school_is_rejected(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);

        $this->actingAs($this->relation)
            ->from(route('admin.schools.show', $this->schoolA))
            ->post(route('admin.schools.classes.store', $this->schoolA), [
                'mode' => 'existing',
                'class_id' => $class->id,
            ])
            ->assertRedirect(route('admin.schools.show', $this->schoolA))
            ->assertSessionHasErrors('class_id');

        $this->assertSame(1, SchoolClass::where('name', 'Grade 5A')->count());
    }

    public function test_duplicate_class_name_is_rejected_case_insensitively(): void
    {
        SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);

        $this->actingAs($this->relation)
            ->from(route('admin.schools.show', $this->schoolA))
            ->post(route('admin.schools.classes.store', $this->schoolA), [
                'mode' => 'new',
                'name' => '  grade 5a  ',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, SchoolClass::where('school_id', $this->schoolA->id)->count());
    }

    public function test_class_with_students_cannot_be_moved_between_schools(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 4C']);
        Student::create(['class_id' => $class->id, 'name' => 'Murid Satu']);

        $this->actingAs($this->relation)
            ->from(route('admin.schools.show', $this->schoolA))
            ->post(route('admin.schools.classes.store', $this->schoolA), [
                'mode' => 'existing',
                'class_id' => $class->id,
            ])
            ->assertSessionHasErrors('class_id');

        // Kelas tetap di sekolah asalnya.
        $this->assertSame($this->schoolB->id, $class->fresh()->school_id);
    }

    public function test_class_with_schedule_cannot_be_moved_between_schools(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 4C']);
        TeachingSchedule::create([
            'school_id' => $this->schoolB->id,
            'class_id' => $class->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        $this->actingAs($this->relation)
            ->from(route('admin.schools.show', $this->schoolA))
            ->post(route('admin.schools.classes.store', $this->schoolA), [
                'mode' => 'existing',
                'class_id' => $class->id,
            ])
            ->assertSessionHasErrors('class_id');

        $this->assertSame($this->schoolB->id, $class->fresh()->school_id);
    }

    // ===== B. Assign / ubah program per kelas =====

    public function test_class_programs_can_be_updated_and_removed(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);
        $class->programs()->attach([$this->coding->id]);

        // Tambah Robotics.
        $this->actingAs($this->relation)
            ->put(route('admin.schools.classes.update', [$this->schoolA, $class]), [
                'name' => 'Grade 5A',
                'program_ids' => [$this->coding->id, $this->robotics->id],
            ])
            ->assertRedirectToRoute('admin.schools.show', $this->schoolA)
            ->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(
            [$this->coding->id, $this->robotics->id],
            $class->fresh()->programs->pluck('id')->all(),
        );

        // Lepas semua program.
        $this->actingAs($this->relation)
            ->put(route('admin.schools.classes.update', [$this->schoolA, $class]), [
                'name' => 'Grade 5A',
                'program_ids' => [],
            ])
            ->assertSessionHas('success');

        $this->assertSame([], $class->fresh()->programs->pluck('id')->all());
    }

    public function test_duplicate_program_assignment_is_prevented(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);

        // Payload berisi program yang sama dua kali.
        $this->actingAs($this->relation)
            ->put(route('admin.schools.classes.update', [$this->schoolA, $class]), [
                'name' => 'Grade 5A',
                'program_ids' => [$this->coding->id, $this->coding->id],
            ])
            ->assertSessionHas('success');

        // Pivot ber-unique(program_id, class_id): tetap satu baris.
        $this->assertSame(1, $class->fresh()->programs()->count());
    }

    public function test_program_must_exist_in_master_program(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);

        $this->actingAs($this->relation)
            ->from(route('admin.schools.show', $this->schoolA))
            ->put(route('admin.schools.classes.update', [$this->schoolA, $class]), [
                'name' => 'Grade 5A',
                'program_ids' => [999999],
            ])
            ->assertSessionHasErrors('program_ids.0');
    }

    // ===== Hapus kelas =====

    public function test_empty_class_can_be_removed_from_workspace(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);

        $this->actingAs($this->relation)
            ->delete(route('admin.schools.classes.destroy', [$this->schoolA, $class]))
            ->assertRedirectToRoute('admin.schools.show', $this->schoolA)
            ->assertSessionHas('success');

        $this->assertModelMissing($class);
    }

    public function test_class_with_students_reports_or_schedules_cannot_be_removed(): void
    {
        $withStudent = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Ada Murid']);
        Student::create(['class_id' => $withStudent->id, 'name' => 'Murid Satu']);

        $withReport = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Ada Laporan']);
        Report::create([
            'coach_id' => $this->coach->id,
            'school_id' => $this->schoolA->id,
            'class_id' => $withReport->id,
            'report_date' => '2026-09-15',
            'lesson_material' => 'Materi',
            'status' => 'pending',
        ]);

        $withSchedule = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Ada Jadwal']);
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $withSchedule->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        foreach ([$withStudent, $withReport, $withSchedule] as $class) {
            $this->actingAs($this->relation)
                ->delete(route('admin.schools.classes.destroy', [$this->schoolA, $class]))
                ->assertSessionHas('error');

            $this->assertModelExists($class);
        }
    }

    // ===== Konsistensi Master Class =====

    public function test_master_class_lists_school_program_and_usage(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);
        $class->programs()->attach($this->coding->id);
        Student::create(['class_id' => $class->id, 'name' => 'Murid Satu']);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $class->id]);

        $this->actingAs($this->relation)
            ->get(route('admin.classes.index'))
            ->assertOk()
            ->assertSee('Grade 5A')
            ->assertSee('SD Workspace A')
            ->assertSee('Coding')
            ->assertSee('1 murid');
    }

    public function test_class_created_in_workspace_appears_in_master_class(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schools.classes.store', $this->schoolA), [
                'mode' => 'new',
                'name' => 'Grade 6B',
            ]);

        $this->actingAs($this->relation)
            ->get(route('admin.classes.index'))
            ->assertOk()
            ->assertSee('Grade 6B');
    }

    // ===== Otorisasi & scope =====

    public function test_pic_cannot_open_school_workspace_or_manage_classes(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);

        // PIC tidak punya schools.view / program_classes.*.
        $this->actingAs($this->picA)
            ->get(route('admin.schools.show', $this->schoolA))
            ->assertForbidden();

        $this->actingAs($this->picA)
            ->post(route('admin.schools.classes.store', $this->schoolA), [
                'mode' => 'new', 'name' => 'Kelas Selundupan',
            ])
            ->assertForbidden();

        $this->actingAs($this->picA)
            ->put(route('admin.schools.classes.update', [$this->schoolA, $class]), [
                'name' => 'Dibajak',
            ])
            ->assertForbidden();

        $this->actingAs($this->picA)
            ->delete(route('admin.schools.classes.destroy', [$this->schoolA, $class]))
            ->assertForbidden();

        $this->assertSame('Grade 5A', $class->fresh()->name);
        $this->assertSame(1, SchoolClass::where('school_id', $this->schoolA->id)->count());
    }

    public function test_coach_and_finance_cannot_manage_school_classes(): void
    {
        $class = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 5A']);

        foreach ([$this->coach, $this->finance] as $user) {
            $this->actingAs($user)
                ->get(route('admin.schools.show', $this->schoolA))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.schools.classes.store', $this->schoolA), [
                    'mode' => 'new', 'name' => 'Kelas Selundupan',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('admin.schools.classes.destroy', [$this->schoolA, $class]))
                ->assertForbidden();
        }

        $this->assertModelExists($class);
    }

    public function test_superadmin_can_manage_school_classes(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('admin.schools.classes.store', $this->schoolA), [
                'mode' => 'new',
                'name' => 'Grade 6B',
                'program_ids' => [$this->coding->id],
            ])
            ->assertRedirectToRoute('admin.schools.show', $this->schoolA);

        $this->assertDatabaseHas('classes', [
            'school_id' => $this->schoolA->id,
            'name' => 'Grade 6B',
        ]);
    }

    public function test_class_of_another_school_cannot_be_edited_through_this_school_url(): void
    {
        $classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 1B']);

        // Manipulasi URL: kelas sekolah B ditempel ke URL sekolah A -> 404.
        $this->actingAs($this->relation)
            ->put(route('admin.schools.classes.update', [$this->schoolA, $classB]), [
                'name' => 'Dibajak',
            ])
            ->assertNotFound();

        $this->actingAs($this->relation)
            ->delete(route('admin.schools.classes.destroy', [$this->schoolA, $classB]))
            ->assertNotFound();

        $this->assertSame('Grade 1B', $classB->fresh()->name);
    }

    public function test_workspace_route_requires_authentication(): void
    {
        $this->get(route('admin.schools.show', $this->schoolA))->assertRedirect(route('login'));
        $this->post(route('admin.schools.classes.store', $this->schoolA), [])->assertRedirect(route('login'));
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
