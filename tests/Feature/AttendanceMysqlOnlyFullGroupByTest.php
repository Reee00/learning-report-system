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
 * Regression MySQL ONLY_FULL_GROUP_BY (bugfix 2026-09-12).
 *
 * Bug: GET /attendance gagal dengan SQLSTATE[42000] 1055 karena query
 * date-grouping menyusun "GROUP BY reports.report_date ORDER BY
 * report_attendances.id DESC, reports.report_date DESC" — ORDER BY memuat
 * kolom non-agregat di luar GROUP BY (id berasal dari latest() default
 * AttendanceScopeService). Query summary per murid punya pola sama.
 *
 * Fix: reorder() melepas order bawaan scope sebelum GROUP BY; urutan akhir
 * hanya kolom grup (reports.report_date / students.name).
 *
 * Test ini WAJIB berjalan di MySQL dengan ONLY_FULL_GROUP_BY aktif — pola
 * lama direproduksi dulu (harus tetap melempar 1055), lalu /attendance dan
 * export CSV (dengan kolom TOTAL HADIR) dikonfirmasi sukses. SQLite tidak
 * meng-enforce pola ini, maka test dijalankan pada koneksi MySQL terpisah:
 *
 *   TEST_MYSQL_DATABASE=<db> [TEST_MYSQL_HOST=127.0.0.1] [TEST_MYSQL_PORT=3308]
 *   [TEST_MYSQL_USERNAME=root] [TEST_MYSQL_PASSWORD=...] \
 *   php artisan test --filter AttendanceMysqlOnlyFullGroupByTest
 *
 * Tanpa TEST_MYSQL_DATABASE test otomatis di-skip (suite SQLite normal
 * tetap memverifikasi logika bisnisnya).
 */
class AttendanceMysqlOnlyFullGroupByTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private User $coachA;
    private User $coachB;
    private User $picA;
    private Student $studentA1;
    private Student $studentA2;
    private Student $studentB1;

    protected function setUp(): void
    {
        if (getenv('TEST_MYSQL_DATABASE') === false || getenv('TEST_MYSQL_DATABASE') === '') {
            $this->markTestSkipped('Set TEST_MYSQL_DATABASE (+ TEST_MYSQL_HOST/PORT/USERNAME/PASSWORD) untuk menjalankan regression test MySQL ONLY_FULL_GROUP_BY.');
        }

        // Override default koneksi phpunit.xml (sqlite :memory:) SEBELUM
        // aplikasi di-boot, sehingga seluruh test — migrasi RefreshDatabase
        // termasuk — berjalan di MySQL. Variabel env yang sudah ada di
        // proses tidak ditimpa .env (immutable reader dotenv).
        foreach ([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => getenv('TEST_MYSQL_HOST') ?: '127.0.0.1',
            'DB_PORT' => getenv('TEST_MYSQL_PORT') ?: '3306',
            'DB_DATABASE' => getenv('TEST_MYSQL_DATABASE'),
            'DB_USERNAME' => getenv('TEST_MYSQL_USERNAME') ?: 'root',
            'DB_PASSWORD' => getenv('TEST_MYSQL_PASSWORD') ?: '',
        ] as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

        parent::setUp();

        // Sanity: server HARUS meng-enforce ONLY_FULL_GROUP_BY, kalau tidak
        // test ini tidak mereproduksi bug aslinya.
        $sqlMode = (string) (DB::selectOne('select @@sql_mode as mode')->mode ?? '');
        $this->assertStringContainsString(
            'ONLY_FULL_GROUP_BY',
            $sqlMode,
            'Server MySQL pengujian harus memiliki ONLY_FULL_GROUP_BY aktif agar regression test ini bermakna.'
        );
    }

    protected function tearDown(): void
    {
        // Kembalikan env proses ke nilai phpunit.xml untuk test berikutnya.
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_DATABASE'] = ':memory:';

        parent::tearDown();
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
     * Pola SQL lama (sebelum fix) HARUS tetap gagal di server ini —
     * membuktikan bug asli direproduksi oleh environment test.
     */
    public function test_old_broken_pattern_still_fails_under_only_full_group_by(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessageMatches('/1055|ONLY_FULL_GROUP_BY/');

        DB::select(
            'select reports.report_date as date, COUNT(*) as total'
            . ' from report_attendances'
            . ' join reports on reports.id = report_attendances.report_id'
            . ' group by reports.report_date'
            . ' order by report_attendances.id desc, reports.report_date desc'
        );
    }

    /**
     * Reproduksi langsung bug: sebelum fix, GET /attendance melempar 500
     * (QueryException 1055) di server ini. Sekarang harus 200.
     *
     * UX 2026-09-13: index menampilkan daftar SEKOLAH (grouping per
     * school_id + name, urut nama) — bukan murid.
     */
    public function test_attendance_index_returns_ok_on_only_full_group_by_mysql(): void
    {
        [$relation] = $this->seedAttendanceData();

        $response = $this->actingAs($relation)
            ->get(route('attendance.index'))
            ->assertOk();

        // School grouping utuh: kartu per sekolah, tanpa nama murid.
        $html = $response->getContent();
        $this->assertStringContainsString('School A', $html);
        $this->assertStringContainsString('School B', $html); // scope relation = global
        $this->assertStringNotContainsString('Andi A', $html);
    }

    public function test_attendance_drilldown_returns_ok_on_only_full_group_by_mysql(): void
    {
        [$relation] = $this->seedAttendanceData();

        // Detail sekolah (grouping per class) dan kelas (daftar sesi).
        $this->actingAs($relation)
            ->get(route('attendance.school', $this->schoolA))
            ->assertOk()
            ->assertSee('Kelas A');

        $this->actingAs($relation)
            ->get(route('attendance.class', [$this->schoolA, $this->classA]))
            ->assertOk()
            ->assertSee('15 Sep 2026'); // sesi terbaru kelas A
    }

    public function test_attendance_index_with_empty_data_returns_ok(): void
    {
        $relation = $this->makeUser('relation.empty@test.test', User::ROLE_RELATION);

        // Index dan export sama-sama harus aman pada dataset kosong.
        $this->actingAs($relation)
            ->get(route('attendance.index'))
            ->assertOk();

        $csv = $this->actingAs($relation)
            ->get(route('attendance.export'))
            ->assertOk()
            ->streamedContent();
        $this->assertStringStartsWith('School,Class,Student', $csv);
        $this->assertStringContainsString('TOTAL HADIR', $csv);
    }

    /**
     * Baris CSV sebagai array asosiatif per murid (str_getcsv menangani
     * quoting fputcsv untuk nilai yang mengandung spasi).
     *
     * @return array<string, array<string, string>> [student] => [kolom => nilai]
     */
    private function csvRowsByStudent(string $csv): array
    {
        $lines = array_filter(explode("\n", trim($csv)));
        $header = str_getcsv((string) reset($lines));

        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            $values = str_getcsv((string) $line);
            $row = array_combine($header, $values);
            $rows[$row['Student']] = $row;
        }

        return $rows;
    }

    public function test_attendance_export_total_hadir_works_on_only_full_group_by_mysql(): void
    {
        [$relation] = $this->seedAttendanceData();

        $csv = $this->actingAs($relation)
            ->get(route('attendance.export'))
            ->assertOk()
            ->streamedContent();

        $rows = $this->csvRowsByStudent($csv);

        // Header: kolom tanggal union lalu TOTAL HADIR paling kanan.
        $lines = array_filter(explode("\n", trim($csv)));
        $this->assertSame(
            ['School', 'Class', 'Student', '2026-09-01', '2026-09-08', '2026-09-15', 'TOTAL HADIR'],
            str_getcsv((string) reset($lines))
        );

        // Andi A: Hadir, Absen, Hadir => 2. Deni A: 3x hadir => 3.
        $this->assertSame('2', $rows['Andi A']['TOTAL HADIR']);
        $this->assertSame('3', $rows['Deni A']['TOTAL HADIR']);
        // Beni B hanya punya sesi 2026-09-01: sel tanggal kosong '-' dan total 1.
        $this->assertSame('-', $rows['Beni B']['2026-09-08']);
        $this->assertSame('1', $rows['Beni B']['TOTAL HADIR']);
    }

    public function test_attendance_filters_and_scope_work_on_mysql(): void
    {
        [$relation, $picA] = $this->seedAttendanceData();

        // Filter rentang tanggal pada index: sesi School B hanya 2026-09-01,
        // jadi rentang mulai 15 Sep menyembunyikan School B.
        $this->actingAs($relation)
            ->get(route('attendance.index', ['date_from' => '2026-09-15', 'date_to' => '2026-09-15']))
            ->assertOk()
            ->assertSee('School A')
            ->assertDontSee('School B');

        // Scope PIC: hanya sekolah plot-nya yang muncul di index.
        $response = $this->actingAs($picA)
            ->get(route('attendance.index'))
            ->assertOk();
        $this->assertStringNotContainsString('School B', $response->getContent());

        // Detail sekolah PIC dibatasi scope sekolah plot-nya.
        $this->actingAs($picA)
            ->get(route('attendance.school', $this->schoolB))
            ->assertForbidden();

        // Export CSV juga dibatasi scope PIC — TOTAL HADIR hanya dari
        // murid sekolah plot-nya (keputusan UX 2026-09-13: akumulasi
        // hanya di dokumen export).
        $picCsv = $this->actingAs($picA)
            ->get(route('attendance.export'))
            ->assertOk()
            ->streamedContent();
        $picRows = $this->csvRowsByStudent($picCsv);
        $this->assertSame('2', $picRows['Andi A']['TOTAL HADIR']);
        $this->assertSame('3', $picRows['Deni A']['TOTAL HADIR']);
        $this->assertArrayNotHasKey('Beni B', $picRows);

        // Relation (scope global) membuka detail kelas sekolah mana pun.
        $this->actingAs($relation)
            ->get(route('attendance.class', [$this->schoolB, $this->classB]))
            ->assertOk();
    }

    public function test_attendance_pagination_works_on_mysql(): void
    {
        // Buat 20 sekolah ber-kehadiran — 2 halaman @ 15 sekolah.
        $relation = $this->makeUser('relation.page@test.test', User::ROLE_RELATION);
        $coach = $this->makeUser('coach.page@test.test', User::ROLE_COACH);

        foreach (range(1, 20) as $i) {
            $school = School::create(['name' => sprintf('SD Paginasi %02d', $i)]);
            $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Kelas P']);
            CoachClass::create(['coach_id' => $coach->id, 'class_id' => $class->id]);
            $student = Student::create(['class_id' => $class->id, 'name' => 'Cici P ' . $i]);

            $report = $this->makeReport($coach, $school, $class, '2026-08-01');
            ReportAttendance::create(['report_id' => $report->id, 'student_id' => $student->id, 'status' => 'present']);
        }

        $page1 = $this->actingAs($relation)
            ->get(route('attendance.index', ['school_page' => 1]))
            ->assertOk();
        $this->assertSame(20, $page1->viewData('schools')->total());
        $this->assertSame(15, $page1->viewData('schools')->count());

        $page2 = $this->actingAs($relation)
            ->get(route('attendance.index', ['school_page' => 2]))
            ->assertOk();
        $this->assertSame(5, $page2->viewData('schools')->count());

        // Urut nama sekolah: halaman 1 diawali SD Paginasi 01.
        $firstPageNames = $page1->viewData('schools')->getCollection()->pluck('school_name');
        $this->assertSame('SD Paginasi 01', (string) $firstPageNames->first());
    }

    /**
     * Seed: sekolah A (2 murid, laporan 3 tanggal) + sekolah B (1 murid,
     * laporan 2026-09-01). PIC hanya di-plot ke sekolah A.
     *
     * @return array{0: User, 1: User} [relation, picA]
     */
    private function seedAttendanceData(): array
    {
        $this->schoolA = School::create(['name' => 'School A']);
        $this->schoolB = School::create(['name' => 'School B']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas B']);

        $this->coachA = $this->makeUser('coach.a@test.test', User::ROLE_COACH);
        $this->coachB = $this->makeUser('coach.b@test.test', User::ROLE_COACH);
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classB->id]);

        $relation = $this->makeUser('relation@test.test', User::ROLE_RELATION);
        $this->picA = $this->makeUser('pic.a@test.test', User::ROLE_SCHOOL_PIC);
        $this->picA->schools()->sync([$this->schoolA->id]);

        $this->studentA1 = Student::create(['class_id' => $this->classA->id, 'name' => 'Andi A']);
        $this->studentA2 = Student::create(['class_id' => $this->classA->id, 'name' => 'Deni A']);
        $this->studentB1 = Student::create(['class_id' => $this->classB->id, 'name' => 'Beni B']);

        foreach (['2026-09-01', '2026-09-08', '2026-09-15'] as $i => $date) {
            $report = $this->makeReport($this->coachA, $this->schoolA, $this->classA, $date);
            ReportAttendance::create(['report_id' => $report->id, 'student_id' => $this->studentA1->id, 'status' => $i === 1 ? 'absent' : 'present']);
            ReportAttendance::create(['report_id' => $report->id, 'student_id' => $this->studentA2->id, 'status' => 'present']);
        }

        $reportB = $this->makeReport($this->coachB, $this->schoolB, $this->classB, '2026-09-01');
        ReportAttendance::create(['report_id' => $reportB->id, 'student_id' => $this->studentB1->id, 'status' => 'present']);

        return [$relation, $this->picA];
    }
}
