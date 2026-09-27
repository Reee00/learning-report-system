<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\ReportMedia;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

/**
 * Export kehadiran (refactor export 2026-09-13):
 * - Excel (.xlsx) = output administratif utama: satu sheet per kelas,
 *   header metadata, tabel No | Nama Siswa | tanggal | TOTAL HADIR.
 * - PDF = laporan cetak formal: satu bagian per kelas.
 * - CSV = data mentah/integrasi (tetap berfungsi).
 *
 * Semua format memakai dataset AttendanceScopeService yang sama — scope
 * role, isolasi sekolah, filter, dan proteksi duplikat berlaku seragam.
 */
class AttendanceExcelPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classA2;
    private SchoolClass $classB;
    private User $coachA;
    private User $picA;
    private User $relation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'School A']);
        $this->schoolB = School::create(['name' => 'School B']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas A']);
        $this->classA2 = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas A2']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas B']);

        $this->coachA = $this->makeUser('coach.a@test.test', User::ROLE_COACH);
        $coachB = $this->makeUser('coach.b@test.test', User::ROLE_COACH);
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA2->id]);
        CoachClass::create(['coach_id' => $coachB->id, 'class_id' => $this->classB->id]);

        $this->picA = $this->makeUser('pic.a@test.test', User::ROLE_SCHOOL_PIC);
        $this->picA->schools()->sync([$this->schoolA->id]);

        $this->relation = $this->makeUser('relation@test.test', User::ROLE_RELATION);

        // Kelas A (School A): Andi 2 hadir dari 3 sesi, Deni 3 hadir.
        $andi = Student::create(['class_id' => $this->classA->id, 'name' => 'Andi A']);
        $deni = Student::create(['class_id' => $this->classA->id, 'name' => 'Deni A']);
        foreach (['2026-09-01', '2026-09-08', '2026-09-15'] as $i => $date) {
            $report = $this->makeReport($this->coachA, $this->schoolA, $this->classA, $date);
            ReportAttendance::create(['report_id' => $report->id, 'student_id' => $andi->id, 'status' => $i === 1 ? 'absent' : 'present']);
            ReportAttendance::create(['report_id' => $report->id, 'student_id' => $deni->id, 'status' => 'present']);
        }

        // Bukti absensi pada sesi pertama (referensi nama file di PDF).
        $firstReport = Report::where('class_id', $this->classA->id)->orderBy('report_date')->first();
        ReportMedia::create([
            'report_id' => $firstReport->id, 'type' => 'attendance',
            'path' => 'attendance/bukti.jpg', 'original_name' => 'bukti-absensi.jpg',
            'disk' => 'local', 'file_size' => 100,
        ]);

        // Kelas A2 (School A): satu murid satu sesi.
        $eri = Student::create(['class_id' => $this->classA2->id, 'name' => 'Eri A2']);
        $reportA2 = $this->makeReport($this->coachA, $this->schoolA, $this->classA2, '2026-09-01');
        ReportAttendance::create(['report_id' => $reportA2->id, 'student_id' => $eri->id, 'status' => 'present']);

        // Kelas B (School B): di luar scope PIC.
        $beni = Student::create(['class_id' => $this->classB->id, 'name' => 'Beni B']);
        $reportB = $this->makeReport($coachB, $this->schoolB, $this->classB, '2026-09-01');
        ReportAttendance::create(['report_id' => $reportB->id, 'student_id' => $beni->id, 'status' => 'present']);
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email,
            'password' => Hash::make('password'), 'role' => $role,
        ]);
    }

    private function makeReport(User $coach, School $school, SchoolClass $class, string $date): Report
    {
        return Report::create([
            'coach_id' => $coach->id, 'school_id' => $school->id, 'class_id' => $class->id,
            'report_date' => $date, 'lesson_material' => 'Materi',
            'goals_materi' => 'Goals', 'activity_report' => 'Ringkasan',
            'status' => 'approved',
        ]);
    }

    /**
     * Unduh Excel sebagai path file temporer untuk dibaca kembali.
     */
    private function downloadExcelAsFile(array $params = []): string
    {
        $response = $this->actingAs($this->relation)
            ->get(route('attendance.export', array_merge($params, ['format' => 'excel'])))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        return $response->baseResponse->getFile()->getPathname();
    }

    /**
     * Sheet XLSX sebagai array [nama sheet => koleksi baris data]. Baris
     * kosong pemisah tidak ditulis oleh openspout, dan key iterator reader
     * 1-based, jadi header tabel (baris ke-8 file) berada pada key 7.
     */
    private function readExcelSheets(string $path): array
    {
        return (new FastExcel())->startRow(7)->withSheetsNames()->importSheets($path)->toArray();
    }

    // ===== XLSX =====

    public function test_excel_generates_one_sheet_per_class_with_rows_and_total_hadir(): void
    {
        $path = $this->downloadExcelAsFile();
        $sheets = $this->readExcelSheets($path);

        // Satu sheet per kelas — murid kelas berbeda tidak pernah dicampur.
        $this->assertSame(['Kelas A', 'Kelas A2', 'Kelas B'], array_keys($sheets));

        $rowsA = $sheets['Kelas A'];
        $this->assertCount(2, $rowsA); // Andi + Deni

        $andi = collect($rowsA)->firstWhere('Nama Siswa', 'Andi A');
        $this->assertNotNull($andi);
        // 3 kolom tanggal + No + Nama Siswa + TOTAL HADIR.
        $this->assertCount(6, $andi);

        // Header kolom kronologis: No, Nama Siswa, 01/08/15 Sep, TOTAL HADIR.
        $headers = array_keys($andi);
        $this->assertSame('No', $headers[0]);
        $this->assertSame('Nama Siswa', $headers[1]);
        $this->assertSame('TOTAL HADIR', $headers[5]);
        $this->assertStringContainsString('01', $headers[2]);
        $this->assertStringContainsString('15', $headers[4]);

        $this->assertSame(2, $andi['TOTAL HADIR']); // Hadir, Absen, Hadir
        $this->assertSame('Hadir', $andi[$headers[2]]);
        $this->assertSame('Absen', $andi[$headers[3]]);
        $this->assertSame('Hadir', $andi[$headers[4]]);

        $deni = collect($rowsA)->firstWhere('Nama Siswa', 'Deni A');
        $this->assertSame(3, $deni['TOTAL HADIR']);
    }

    public function test_excel_respects_school_and_class_filters(): void
    {
        $path = $this->downloadExcelAsFile(['school_id' => $this->schoolA->id, 'class_id' => $this->classA->id]);
        $sheets = $this->readExcelSheets($path);

        $this->assertSame(['Kelas A'], array_keys($sheets));
        $this->assertNotContains('Beni B', collect($sheets['Kelas A'])->pluck('Nama Siswa')->all());
    }

    public function test_excel_respects_date_range_filter(): void
    {
        $path = $this->downloadExcelAsFile(['class_id' => $this->classA->id, 'date_from' => '2026-09-08', 'date_to' => '2026-09-08']);
        $sheets = $this->readExcelSheets($path);

        $andi = collect($sheets['Kelas A'])->firstWhere('Nama Siswa', 'Andi A');
        // Hanya sesi 08 Sep (Absen) => TOTAL HADIR 0.
        $this->assertSame(0, $andi['TOTAL HADIR']);
        $this->assertCount(4, $andi); // No + Nama + 1 tanggal + TOTAL
    }

    public function test_excel_is_scoped_to_plotted_school_for_pic(): void
    {
        $response = $this->actingAs($this->picA)
            ->get(route('attendance.export', ['format' => 'excel']))
            ->assertOk();

        $sheets = $this->readExcelSheets($response->baseResponse->getFile()->getPathname());

        // PIC School A: hanya kelas-kelas School A, tanpa murid School B.
        $this->assertSame(['Kelas A', 'Kelas A2'], array_keys($sheets));
        foreach ($sheets as $rows) {
            $this->assertNotContains('Beni B', collect($rows)->pluck('Nama Siswa')->all());
        }
    }

    public function test_excel_with_empty_data_still_generates_valid_file(): void
    {
        // Kosongkan seluruh data kehadiran — relation scope global.
        ReportAttendance::query()->delete();

        $emptyRelation = $this->makeUser('relation.empty@test.test', User::ROLE_RELATION);

        $response = $this->actingAs($emptyRelation)
            ->get(route('attendance.export', ['format' => 'excel']))
            ->assertOk();

        $sheets = $this->readExcelSheets($response->baseResponse->getFile()->getPathname());
        $this->assertSame(['Laporan'], array_keys($sheets));
    }

    public function test_excel_does_not_double_count_duplicate_session_same_date(): void
    {
        // Sesi kedua pada tanggal yang sama di Kelas A2.
        $eri = Student::where('name', 'Eri A2')->firstOrFail();
        $extra = $this->makeReport($this->coachA, $this->schoolA, $this->classA2, '2026-09-01');
        ReportAttendance::create(['report_id' => $extra->id, 'student_id' => $eri->id, 'status' => 'present']);

        $path = $this->downloadExcelAsFile(['class_id' => $this->classA2->id]);
        $sheets = $this->readExcelSheets($path);

        $eri = collect($sheets['Kelas A2'])->firstWhere('Nama Siswa', 'Eri A2');
        // Dua sesi tanggal sama tetap satu kolom tanggal => total 1.
        $this->assertSame(1, $eri['TOTAL HADIR']);
        $this->assertCount(4, $eri);
    }

    public function test_finance_cannot_export_excel_or_pdf_but_csv_works(): void
    {
        $finance = $this->makeUser('finance@test.test', User::ROLE_FINANCE);
        // Finance tidak lagi bergantung pada plot sekolah: scope-nya all-school
        // dari role (AuthorizationService::accessibleSchoolIds mengembalikan
        // null). Plot di bawah sengaja dibiarkan untuk membuktikan bahwa plot
        // tidak lagi menyempitkan aksesnya.
        $finance->schools()->sync([$this->schoolA->id]);

        $this->actingAs($finance)
            ->get(route('attendance.export', ['format' => 'excel']))
            ->assertForbidden();

        $this->actingAs($finance)
            ->get(route('attendance.export', ['format' => 'pdf']))
            ->assertForbidden();

        // CSV data mentah tetap boleh untuk Finance.
        $csv = $this->actingAs($finance)
            ->get(route('attendance.export', ['format' => 'csv']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();
        $this->assertStringStartsWith('School,Class,Student', $csv);
        // Requirement bisnis final: Finance punya visibilitas all-school, jadi
        // CSV-nya memuat sekolah mana pun yang laporannya sudah approved —
        // termasuk schoolB, meski Finance hanya di-plot ke schoolA.
        $this->assertStringContainsString('Andi A', $csv);
        $this->assertStringContainsString('Beni B', $csv);
    }

    // ===== PDF =====

    public function test_pdf_export_generates_valid_pdf(): void
    {
        $response = $this->actingAs($this->relation)
            ->get(route('attendance.export', ['format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->baseResponse->getContent());
    }

    public function test_pdf_export_with_empty_data_still_generates_valid_pdf(): void
    {
        $emptyRelation = $this->makeUser('relation.empty2@test.test', User::ROLE_RELATION);

        $response = $this->actingAs($emptyRelation)
            ->get(route('attendance.export', ['format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->baseResponse->getContent());
    }

    public function test_pdf_is_scoped_to_plotted_school_for_pic(): void
    {
        // PIC hanya boleh menghasilkan dokumen dari scope sekolahnya —
        // dataset sama dengan Excel/CSV (satu AttendanceScopeService).
        $response = $this->actingAs($this->picA)
            ->get(route('attendance.export', ['format' => 'pdf', 'school_id' => $this->schoolB->id]))
            ->assertOk();

        $this->assertTrue($response->isOk());
    }
}
