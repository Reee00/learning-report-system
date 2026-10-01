<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Finance — akses kehadiran GLOBAL (review meeting LRS 2026-10-01).
 *
 * Finance TIDAK butuh plotting sekolah: scope-nya seluruh sekolah, dan itu
 * datang dari role (`AuthorizationService::accessibleSchoolIds()` → null),
 * bukan dari baris `school_user`. Karena itu Finance dapat menelusuri
 * Attendance → Sekolah → Kelas → Tanggal → Murid untuk sekolah mana pun,
 * memakai filter sekolah/kelas/tanggal yang sudah ada, dan mengekspor
 * kehadiran seluruh sekolah dalam format CSV, Excel, maupun PDF.
 *
 * Yang TIDAK berubah: Finance tetap tidak memperoleh capability di luar
 * tugasnya (master data, review laporan, manajemen coach, dsb.).
 */
class FinanceAttendanceAccessTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private Report $reportA;
    private Report $reportB;
    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'School Alfa']);
        $this->schoolB = School::create(['name' => 'School Beta']);

        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas Alfa']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas Beta']);

        $coachA = $this->makeUser('coach.alfa@test.test', User::ROLE_COACH);
        $coachB = $this->makeUser('coach.beta@test.test', User::ROLE_COACH);
        CoachClass::create(['coach_id' => $coachA->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $coachB->id, 'class_id' => $this->classB->id]);

        $andi = Student::create(['class_id' => $this->classA->id, 'name' => 'Andi Alfa']);
        $beni = Student::create(['class_id' => $this->classB->id, 'name' => 'Beni Beta']);

        $this->reportA = $this->makeReport($coachA, $this->schoolA, $this->classA, '2026-09-01');
        ReportAttendance::create(['report_id' => $this->reportA->id, 'student_id' => $andi->id, 'status' => 'present']);

        $reportA2 = $this->makeReport($coachA, $this->schoolA, $this->classA, '2026-09-15');
        ReportAttendance::create(['report_id' => $reportA2->id, 'student_id' => $andi->id, 'status' => 'absent']);

        $this->reportB = $this->makeReport($coachB, $this->schoolB, $this->classB, '2026-09-08');
        ReportAttendance::create(['report_id' => $this->reportB->id, 'student_id' => $beni->id, 'status' => 'present']);

        // Finance: TANPA plotting apa pun — tidak ada baris school_user,
        // school_id NULL. Sengaja begitu supaya test gagal bila aksesnya
        // diam-diam bergantung pada assignment sekolah.
        $this->finance = $this->makeUser('finance@test.test', User::ROLE_FINANCE);
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name'     => 'User '.$email,
            'email'    => $email,
            'password' => Hash::make('password'),
            'role'     => $role,
        ]);
    }

    private function makeReport(User $coach, School $school, SchoolClass $class, string $date): Report
    {
        return Report::create([
            'coach_id'        => $coach->id,
            'school_id'       => $school->id,
            'class_id'        => $class->id,
            'report_date'     => $date,
            'lesson_material' => 'Materi',
            'goals_materi'    => 'Goals',
            'activity_report' => 'Ringkasan',
            'status'          => 'approved',
        ]);
    }

    // =====================================================================
    // 7. Tanpa baris school_user — dipastikan lebih dahulu, karena seluruh
    //    test di bawah berdiri di atas premis ini.
    // =====================================================================

    public function test_finance_needs_no_school_user_row_for_attendance_access(): void
    {
        $this->assertNull($this->finance->school_id);
        $this->assertSame(0, DB::table('school_user')->where('user_id', $this->finance->id)->count());
        $this->assertSame([], $this->finance->assignedSchoolIds());

        // Meski tanpa plotting sama sekali, pintu masuk attendance terbuka.
        $this->actingAs($this->finance)->get(route('attendance.index'))->assertOk();
    }

    // =====================================================================
    // 1–4. Seluruh sekolah, tanpa plotting
    // =====================================================================

    public function test_finance_login_reaches_the_attendance_module(): void
    {
        $this->actingAs($this->finance)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('School Alfa')
            ->assertSee('School Beta');
    }

    public function test_finance_sees_attendance_of_school_a(): void
    {
        $this->actingAs($this->finance)
            ->get(route('attendance.school', $this->schoolA))
            ->assertOk()
            ->assertSee('Kelas Alfa');

        $this->actingAs($this->finance)
            ->get(route('attendance.class', [$this->schoolA, $this->classA]))
            ->assertOk()
            ->assertSee('Kelas Alfa');

        $this->actingAs($this->finance)
            ->get(route('attendance.session', $this->reportA))
            ->assertOk()
            ->assertSee('Andi Alfa');
    }

    public function test_finance_sees_attendance_of_school_b(): void
    {
        $this->actingAs($this->finance)
            ->get(route('attendance.school', $this->schoolB))
            ->assertOk()
            ->assertSee('Kelas Beta');

        $this->actingAs($this->finance)
            ->get(route('attendance.class', [$this->schoolB, $this->classB]))
            ->assertOk()
            ->assertSee('Kelas Beta');

        $this->actingAs($this->finance)
            ->get(route('attendance.session', $this->reportB))
            ->assertOk()
            ->assertSee('Beni Beta');
    }

    public function test_finance_sees_every_school_without_any_plotting(): void
    {
        // Dua sekolah di sistem, satu di antaranya bahkan tidak punya kaitan
        // apa pun dengan user ini.
        $this->assertSame(2, School::count());

        $response = $this->actingAs($this->finance)
            ->get(route('attendance.index'))
            ->assertOk();

        $response->assertSee('School Alfa');
        $response->assertSee('School Beta');

        // Drill-down ke sekolah mana pun tetap terbuka.
        $this->actingAs($this->finance)->get(route('attendance.school', $this->schoolA))->assertOk();
        $this->actingAs($this->finance)->get(route('attendance.school', $this->schoolB))->assertOk();
    }

    // =====================================================================
    // 5. Filter sekolah / kelas / tanggal
    // =====================================================================

    public function test_finance_can_filter_by_school(): void
    {
        $csv = $this->csvContent(['school_id' => $this->schoolA->id]);

        $this->assertStringContainsString('Andi Alfa', $csv);
        $this->assertStringNotContainsString('Beni Beta', $csv);
    }

    public function test_finance_can_filter_by_class(): void
    {
        $csv = $this->csvContent(['class_id' => $this->classB->id]);

        $this->assertStringContainsString('Beni Beta', $csv);
        $this->assertStringNotContainsString('Andi Alfa', $csv);
    }

    public function test_finance_can_filter_by_date_range(): void
    {
        // Hanya sesi 2026-09-15 (Andi: absen) yang masuk rentang ini.
        $csv = $this->csvContent([
            'date_from' => '2026-09-10',
            'date_to'   => '2026-09-30',
        ]);

        $this->assertStringContainsString('2026-09-15', $csv);
        $this->assertStringNotContainsString('2026-09-01', $csv);
        $this->assertStringNotContainsString('2026-09-08', $csv);
        $this->assertStringNotContainsString('Beni Beta', $csv);
    }

    public function test_finance_can_filter_the_class_drill_down_by_date(): void
    {
        $this->actingAs($this->finance)
            ->get(route('attendance.class', [
                $this->schoolA,
                $this->classA,
                'date_from' => '2026-09-10',
                'date_to'   => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSee('15 Sep 2026')
            ->assertDontSee('01 Sep 2026');
    }

    // =====================================================================
    // 6. Export seluruh sekolah sesuai filter
    // =====================================================================

    public function test_finance_can_export_attendance_across_every_school(): void
    {
        $csv = $this->csvContent();

        // Tanpa filter: seluruh sekolah, bukan hanya satu.
        $this->assertStringContainsString('Andi Alfa', $csv);
        $this->assertStringContainsString('Beni Beta', $csv);
        $this->assertStringContainsString('School Alfa', $csv);
        $this->assertStringContainsString('School Beta', $csv);
    }

    public function test_finance_export_respects_the_combined_filter(): void
    {
        $csv = $this->csvContent([
            'school_id' => $this->schoolA->id,
            'class_id'  => $this->classA->id,
            'date_from' => '2026-09-01',
            'date_to'   => '2026-09-10',
        ]);

        $this->assertStringContainsString('Andi Alfa', $csv);
        $this->assertStringNotContainsString('Beni Beta', $csv);
    }

    public function test_finance_can_export_attendance_in_csv_excel_and_pdf(): void
    {
        // CSV.
        $csv = $this->actingAs($this->finance)
            ->get(route('attendance.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('Andi Alfa', $csv);
        $this->assertStringContainsString('Beni Beta', $csv);

        // Excel — satu workbook, jadi seluruh sekolah ikut.
        $excel = $this->actingAs($this->finance)
            ->get(route('attendance.export', ['format' => 'excel']))
            ->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $excel->headers->get('content-type')
        );
        // `?format=xlsx` adalah alias yang sudah ada — harus sama-sama lolos.
        $this->actingAs($this->finance)
            ->get(route('attendance.export', ['format' => 'xlsx']))
            ->assertOk();

        // PDF.
        $pdf = $this->actingAs($this->finance)
            ->get(route('attendance.export', ['format' => 'pdf']))
            ->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());
    }

    public function test_finance_excel_contains_every_school_and_honours_the_filter(): void
    {
        // Tanpa filter: workbook memuat sheet untuk kelas dari KEDUA sekolah.
        $semua = $this->actingAs($this->finance)
            ->get(route('attendance.export', ['format' => 'excel']))
            ->assertOk()
            ->getFile()->getPathname();

        $this->assertGreaterThan(1, $this->sheetCount($semua), 'Satu sheet per kelas, bukan satu untuk seluruh sistem.');

        // Dengan filter sekolah: hanya kelas sekolah itu yang masuk.
        $satuSekolah = $this->actingAs($this->finance)
            ->get(route('attendance.export', ['format' => 'excel', 'school_id' => $this->schoolA->id]))
            ->assertOk()
            ->getFile()->getPathname();

        $this->assertSame(1, $this->sheetCount($satuSekolah));

        @unlink($semua);
        @unlink($satuSekolah);
    }

    /**
     * Jumlah sheet di dalam berkas XLSX — dibaca langsung dari arsipnya supaya
     * test tidak bergantung pada pustaka pembaca tertentu.
     */
    private function sheetCount(string $path): int
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'Berkas XLSX tidak dapat dibuka.');

        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();

        return substr_count($workbook, '<sheet ');
    }

    // =====================================================================
    // 8. Tidak ada akses ke modul di luar permission Finance
    // =====================================================================

    public function test_finance_gains_no_access_to_unrelated_modules(): void
    {
        foreach ([
            'admin.dashboard',
            'admin.users.index',
            'admin.schools.index',
            'admin.classes.index',
            'admin.programs.index',
            'admin.coaches.index',
            'admin.reports.index',
            'admin.schedules.index',
        ] as $route) {
            $this->actingAs($this->finance)
                ->get(route($route))
                ->assertForbidden("Finance tidak boleh mencapai {$route}.");
        }
    }

    public function test_finance_cannot_open_report_review_master_data_or_rosters(): void
    {
        $this->actingAs($this->finance)
            ->get(route('admin.reports.show', $this->reportA))
            ->assertForbidden();

        $this->actingAs($this->finance)
            ->get(route('admin.reports.download', $this->reportA))
            ->assertForbidden();

        $this->actingAs($this->finance)
            ->get(route('students.show', $this->classA))
            ->assertForbidden();

        $this->actingAs($this->finance)
            ->get(route('admin.coaches.show', $this->reportA->coach_id))
            ->assertForbidden();
    }

    public function test_finance_cannot_manipulate_the_url_to_escape_its_capabilities(): void
    {
        // Menambahkan parameter yang menyerupai akses lain tidak mengubah apa
        // pun: capability diperiksa lebih dahulu, bukan filter.
        $this->actingAs($this->finance)
            ->get(route('admin.reports.index', ['status' => 'approved', 'school_id' => $this->schoolA->id]))
            ->assertForbidden();

        $this->actingAs($this->finance)
            ->get(route('admin.reports.download', [$this->reportA, 'school_id' => $this->schoolA->id]))
            ->assertForbidden();

        $this->actingAs($this->finance)
            ->get(route('students.show', [$this->classA, 'school_id' => $this->schoolA->id]))
            ->assertForbidden();

        // Sebaliknya, filter pada rute yang MEMANG miliknya tetap bekerja
        // untuk sekolah mana pun — termasuk export ke format formal.
        $this->actingAs($this->finance)
            ->get(route('attendance.school', [$this->schoolB, 'school_id' => $this->schoolB->id]))
            ->assertOk();

        $this->actingAs($this->finance)
            ->get(route('attendance.export', ['format' => 'pdf', 'school_id' => $this->schoolB->id]))
            ->assertOk();

        // Parameter sekolah yang tidak ada tetap ditolak validasi filter.
        $this->actingAs($this->finance)
            ->get(route('attendance.export', ['format' => 'excel', 'school_id' => 999999]))
            ->assertSessionHasErrors('school_id');
    }

    /**
     * Isi CSV hasil export attendance untuk user Finance.
     */
    private function csvContent(array $params = []): string
    {
        return $this->actingAs($this->finance)
            ->get(route('attendance.export', array_merge($params, ['format' => 'csv'])))
            ->assertOk()
            ->streamedContent();
    }
}
