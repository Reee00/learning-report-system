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
use Tests\TestCase;

/**
 * Kehadiran — hierarki UX 2026-09-13: drill-down
 * Attendance (sekolah) → Kelas → Tanggal → Murid.
 *
 * Verifies:
 * - Index hanya menampilkan SEKOLAH dengan data kehadiran dalam scope —
 *   bukan daftar murid/tanggal, dan tanpa akumulasi (TOTAL HADIR).
 * - Detail sekolah menampilkan kelas-kelas ber-kehadiran milik sekolah itu.
 * - Detail kelas menampilkan sesi (tanggal); detail tanggal menampilkan
 *   tabel Murid | Status + bukti absensi.
 * - Isolasi scope: PIC tidak melihat data sekolah lain (403/404/tersembunyi).
 * - Kolom TOTAL HADIR pada export hanya dihitung dari dataset ter-scope,
 *   mengikuti filter aktif, tanpa hitungan ganda.
 */
class AttendanceGroupingTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private User $coachA;
    private User $picA;
    private Student $studentA1;
    private Student $studentB1;
    private Report $reportA1;
    private Report $reportA2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'School A']);
        $this->schoolB = School::create(['name' => 'School B']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas B']);

        $this->coachA = $this->makeUser('coach.a@test.test', User::ROLE_COACH);
        $coachB = $this->makeUser('coach.b@test.test', User::ROLE_COACH);
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $coachB->id, 'class_id' => $this->classB->id]);

        $this->picA = $this->makeUser('pic.a@test.test', User::ROLE_SCHOOL_PIC);
        $this->picA->schools()->sync([$this->schoolA->id]);

        $this->studentA1 = Student::create(['class_id' => $this->classA->id, 'name' => 'Andi A']);
        $this->studentB1 = Student::create(['class_id' => $this->classB->id, 'name' => 'Beni B']);

        $this->reportA1 = $this->makeReport($this->coachA, $this->schoolA, $this->classA, '2026-09-01');
        $this->reportA2 = $this->makeReport($this->coachA, $this->schoolA, $this->classA, '2026-09-08');
        $reportB1 = $this->makeReport($coachB, $this->schoolB, $this->classB, '2026-09-01');

        ReportAttendance::create(['report_id' => $this->reportA1->id, 'student_id' => $this->studentA1->id, 'status' => 'present']);
        ReportAttendance::create(['report_id' => $this->reportA2->id, 'student_id' => $this->studentA1->id, 'status' => 'absent']);
        ReportAttendance::create(['report_id' => $reportB1->id, 'student_id' => $this->studentB1->id, 'status' => 'present']);
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email,
            'password' => Hash::make('password'), 'role' => $role,
        ]);
    }

    private function makeReport(User $coach, School $school, SchoolClass $class, string $date, string $status = 'approved'): Report
    {
        return Report::create([
            'coach_id' => $coach->id, 'school_id' => $school->id, 'class_id' => $class->id,
            'report_date' => $date, 'lesson_material' => 'Materi',
            'goals_materi' => 'Goals', 'activity_report' => 'Ringkasan',
            'status' => $status,
        ]);
    }

    // ===== 1. INDEX: daftar sekolah =====

    public function test_index_lists_schools_with_summaries(): void
    {
        $html = $this->actingAs($this->picA)
            ->get(route('attendance.index'))
            ->assertOk()
            ->getContent();

        // PIC scope: hanya School A dengan ringkasannya (2 sesi, 1 kelas).
        $this->assertStringContainsString('School A', $html);
        $this->assertStringContainsString('1 kelas', $html);
        $this->assertStringContainsString('2 sesi kehadiran', $html);
        // School B tersaring; nama murid tidak pernah muncul di index.
        $this->assertStringNotContainsString('School B', $html);
        $this->assertStringNotContainsString('Andi A', $html);
        $this->assertStringNotContainsString('Beni B', $html);
    }

    public function test_index_search_filters_schools(): void
    {
        $relation = $this->makeUser('relation@test.test', User::ROLE_RELATION);

        $response = $this->actingAs($relation)
            ->get(route('attendance.index', ['search' => 'School B']))
            ->assertOk();

        $response->assertSee('School B')->assertDontSee('School A');
    }

    /**
     * Keputusan UX 2026-09-13: akumulasi per murid hanya ada di dokumen
     * unduh — halaman mana pun tidak boleh menampilkan panel akumulasi.
     */
    public function test_pages_do_not_show_student_accumulation(): void
    {
        $pages = [
            route('attendance.index'),
            route('attendance.school', $this->schoolA),
            route('attendance.class', [$this->schoolA, $this->classA]),
            route('attendance.session', $this->reportA1),
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($this->picA)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('Akumulasi', $html, "Halaman {$url} menampilkan akumulasi.");
            $this->assertStringNotContainsString('TOTAL HADIR', $html, "Halaman {$url} menampilkan TOTAL HADIR.");
        }
    }

    /**
     * Keputusan UX 2026-09-14: tombol unduh HANYA di detail kelas terpilih.
     * Landing page dan detail sekolah tidak boleh memuat tautan export —
     * unduh menunggu konteks kelas dipilih.
     */
    public function test_download_button_only_shown_on_class_detail_page(): void
    {
        // Landing page & detail sekolah: tanpa tautan unduh.
        foreach ([route('attendance.index'), route('attendance.school', $this->schoolA)] as $url) {
            $html = $this->actingAs($this->picA)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('attendance/export', $html, "Halaman {$url} memuat tautan unduh.");
            $this->assertStringNotContainsString('Unduh', $html, "Halaman {$url} memuat tombol unduh.");
        }

        // Detail kelas: tombol unduh ada dan otomatis membawa konteks
        // sekolah + kelas halaman saat itu.
        $html = $this->actingAs($this->picA)
            ->get(route('attendance.class', [$this->schoolA, $this->classA]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Unduh Excel', $html);
        $this->assertStringContainsString('Unduh PDF', $html);
        $this->assertStringContainsString('school_id=' . $this->schoolA->id, $html);
        $this->assertStringContainsString('class_id=' . $this->classA->id, $html);
    }

    /**
     * Manipulasi query param tidak bisa menarik data kelas/sekolah lain:
     * filter class_id diterapkan DI DALAM dataset ter-scope, bukan
     * menggantikannya.
     */
    public function test_export_query_manipulation_cannot_pull_other_school_class(): void
    {
        // PIC A menyelundupkan class_id milik School B.
        $csv = $this->actingAs($this->picA)
            ->get(route('attendance.export', ['class_id' => $this->classB->id, 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        // Scope PIC tetap School A → filter kelas B menghasilkan kosong.
        $this->assertStringNotContainsString('Beni B', $csv);
        $this->assertStringNotContainsString('Kelas B', $csv);
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $this->assertCount(1, $rows); // hanya header
    }

    // ===== 2. SCHOOL DETAIL: daftar kelas =====

    public function test_school_detail_lists_classes_with_summaries(): void
    {
        $html = $this->actingAs($this->picA)
            ->get(route('attendance.school', $this->schoolA))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Kelas A', $html);
        $this->assertStringContainsString('2 sesi kehadiran', $html);
        $this->assertStringContainsString('1 murid tercatat', $html);
        $this->assertStringContainsString('08 Sep 2026', $html); // tanggal terakhir
        // Kelas sekolah lain tidak pernah muncul.
        $this->assertStringNotContainsString('Kelas B', $html);
        $this->assertStringNotContainsString('Beni B', $html);
    }

    public function test_school_detail_rejects_cross_school_access(): void
    {
        $this->actingAs($this->picA)
            ->get(route('attendance.school', $this->schoolB))
            ->assertForbidden();
    }

    // ===== 3. CLASS DETAIL: daftar tanggal/sesi =====

    public function test_class_detail_lists_sessions_by_date(): void
    {
        $html = $this->actingAs($this->picA)
            ->get(route('attendance.class', [$this->schoolA, $this->classA]))
            ->assertOk()
            ->getContent();

        // Dua sesi: 01 Sep dan 08 Sep 2026, dengan jumlah murid tercatat.
        $this->assertStringContainsString('01 Sep 2026', $html);
        $this->assertStringContainsString('08 Sep 2026', $html);
        $this->assertStringContainsString('1 murid tercatat', $html);
        $this->assertStringNotContainsString('Beni B', $html);
    }

    public function test_class_detail_respects_date_filter(): void
    {
        $html = $this->actingAs($this->picA)
            ->get(route('attendance.class', [
                $this->schoolA, $this->classA,
                'date_from' => '2026-09-08', 'date_to' => '2026-09-08',
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('08 Sep 2026', $html);
        $this->assertStringNotContainsString('01 Sep 2026', $html);
    }

    public function test_class_detail_rejects_cross_school_access(): void
    {
        // URL sekolah lain (School B bukan scope PIC) → 403.
        $this->actingAs($this->picA)
            ->get(route('attendance.class', [$this->schoolB, $this->classB]))
            ->assertForbidden();
    }

    public function test_class_detail_returns_404_when_class_belongs_to_other_school(): void
    {
        // Kelas B bukan milik School A — 404 agar keberadaannya tidak bocor,
        // bahkan untuk role dengan scope global (Relation).
        $relation = $this->makeUser('relation2@test.test', User::ROLE_RELATION);
        $this->actingAs($relation)
            ->get(route('attendance.class', [$this->schoolA, $this->classB]))
            ->assertNotFound();

        $this->actingAs($this->picA)
            ->get(route('attendance.class', [$this->schoolA, $this->classB]))
            ->assertNotFound();
    }

    /**
     * Laporan draft tidak terlihat untuk PIC — scope PIC hanya approved.
     */
    public function test_draft_report_is_hidden_from_pic(): void
    {
        $draft = $this->makeReport($this->coachA, $this->schoolA, $this->classA, '2026-09-15', 'draft');
        ReportAttendance::create(['report_id' => $draft->id, 'student_id' => $this->studentA1->id, 'status' => 'present']);

        $html = $this->actingAs($this->picA)
            ->get(route('attendance.class', [$this->schoolA, $this->classA]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('15 Sep 2026', $html);

        // Detail sesi draft → 404 (tidak dalam scope PIC).
        $this->actingAs($this->picA)
            ->get(route('attendance.session', $draft))
            ->assertNotFound();
    }

    // ===== 4. SESSION DETAIL: tabel murid + bukti =====

    public function test_session_detail_lists_students_with_status(): void
    {
        ReportMedia::create([
            'report_id' => $this->reportA1->id, 'type' => 'attendance',
            'path' => 'attendance/test.jpg', 'original_name' => 'test.jpg',
            'disk' => 'local', 'file_size' => 100,
        ]);

        $response = $this->actingAs($this->picA)
            ->get(route('attendance.session', $this->reportA1))
            ->assertOk();

        $response->assertSee('Andi A')->assertSee('Hadir');
        // Bukti absensi ikut ditampilkan.
        $response->assertSee('Bukti Absensi');
    }

    public function test_session_detail_rejects_cross_school_report(): void
    {
        $reportB1 = Report::where('school_id', $this->schoolB->id)->first();

        $this->actingAs($this->picA)
            ->get(route('attendance.session', $reportB1))
            ->assertForbidden();
    }

    // ===== 5. EXPORT: TOTAL HADIR =====

    /**
     * Kolom TOTAL HADIR pada CSV export: hitungan "Hadir" per murid dari
     * dataset ter-scope, mengikuti filter tanggal aktif, tanpa data murid
     * sekolah lain.
     */
    public function test_export_total_hadir_respects_scope_and_filters(): void
    {
        $csv = $this->actingAs($this->picA)
            ->get(route('attendance.export'))
            ->assertOk()
            ->streamedContent();

        // Header: kolom tanggal lalu TOTAL HADIR paling kanan.
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $this->assertSame(
            ['School', 'Class', 'Student', '2026-09-01', '2026-09-08', 'TOTAL HADIR'],
            $rows[0]
        );

        // PIC A scope: hanya School A. Andi A hadir 1 dari 2 sesi.
        $this->assertSame(
            ['School A', 'Kelas A', 'Andi A', 'Hadir', 'Absen', '1'],
            $rows[1]
        );
        $this->assertCount(2, $rows); // header + Andi A — Beni B (School B) tersaring
        $this->assertStringNotContainsString('Beni B', $csv);

        // Filter tanggal menyempitkan perhitungan (sesi 08: Absen => 0).
        $filtered = $this->actingAs($this->picA)
            ->get(route('attendance.export', ['date_from' => '2026-09-08', 'date_to' => '2026-09-08']))
            ->assertOk()
            ->streamedContent();

        $filteredRows = array_map('str_getcsv', array_filter(explode("\n", trim($filtered))));
        $this->assertSame(
            ['School', 'Class', 'Student', '2026-09-08', 'TOTAL HADIR'],
            $filteredRows[0]
        );
        $this->assertSame(
            ['School A', 'Kelas A', 'Andi A', 'Absen', '0'],
            $filteredRows[1]
        );
    }

    /**
     * Dua sesi pada tanggal yang sama untuk murid yang sama tidak boleh
     * dihitung dua kali pada TOTAL HADIR (matriks unik per [murid][tanggal];
     * DB sendiri sudah menolak baris ganda via unique report_id+student_id).
     */
    public function test_export_total_hadir_does_not_double_count_duplicate_rows(): void
    {
        $extraReport = $this->makeReport($this->coachA, $this->schoolA, $this->classA, '2026-09-01');
        ReportAttendance::create([
            'report_id' => $extraReport->id,
            'student_id' => $this->studentA1->id,
            'status' => 'present',
        ]);

        $csv = $this->actingAs($this->picA)
            ->get(route('attendance.export'))
            ->assertOk()
            ->streamedContent();

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        // Andi A: Hadir (2 sesi tanggal sama) + Absen => TOTAL HADIR tetap 1.
        $this->assertSame(
            ['School A', 'Kelas A', 'Andi A', 'Hadir', 'Absen', '1'],
            $rows[1]
        );
    }
}
