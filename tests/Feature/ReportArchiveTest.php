<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Arsip Laporan (admin.reports.index) — meeting 2026-09-27.
 *
 * Arsip BUKAN penyimpanan kedua: halaman ini membaca tabel `reports` yang sama
 * dan menampilkannya sebagai riwayat Sekolah → Kelas, baca-saja (hanya Detail
 * dan Unduh laporan yang sudah disetujui). Tabel arsip terpisah tidak dibuat.
 *
 * Yang diuji di sini:
 * - struktur arsip: pengelompokan sekolah → kelas dan kelengkapan filternya;
 * - isolasi lintas sekolah pada daftar, filter, dan URL langsung;
 * - scope per role: Relation/SuperAdmin global, PIC sekolah plot-nya,
 *   Teacher School hanya laporan disetujui, Finance & Coach tanpa akses.
 */
class ReportArchiveTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA1;
    private SchoolClass $classA2;
    private SchoolClass $classB1;
    private User $coachA;
    private User $coachB;
    private User $relation;
    private User $superadmin;
    private User $picA;
    private User $teacherA;
    private User $finance;
    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Arsip A']);
        $this->schoolB = School::create(['name' => 'SD Arsip B']);

        $this->classA1 = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 1A']);
        $this->classA2 = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 2A']);
        $this->classB1 = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 1B']);

        $this->coachA = $this->makeUser(User::ROLE_COACH, 'coach.a');
        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA1->id]);

        $this->coachB = $this->makeUser(User::ROLE_COACH, 'coach.b');
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classB1->id]);

        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation');
        $this->superadmin = $this->makeUser(User::ROLE_SUPERADMIN, 'superadmin');
        $this->finance = $this->makeUser(User::ROLE_FINANCE, 'finance');
        $this->coach = $this->makeUser(User::ROLE_COACH, 'coach.plain');

        $this->picA = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.a');
        $this->picA->schools()->attach($this->schoolA->id);

        $this->teacherA = User::create([
            'name' => 'Teacher A',
            'email' => 'teacher.a@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER_SCHOOL,
            'school_id' => $this->schoolA->id,
        ]);
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

    private function report(
        User $coach,
        School $school,
        SchoolClass $class,
        string $date,
        string $status = 'submitted',
    ): Report {
        return Report::create([
            'coach_id' => $coach->id,
            'school_id' => $school->id,
            'class_id' => $class->id,
            'report_date' => $date,
            'lesson_material' => 'Materi '.$date,
            'goals_materi' => 'Goals',
            'activity_report' => 'Aktivitas',
            'status' => $status,
        ]);
    }

    // ===== 1. Struktur arsip =====

    public function test_archive_groups_reports_by_school_then_class(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02', 'approved');
        $this->report($this->coachA, $this->schoolA, $this->classA2, '2026-02-03');
        $this->report($this->coachB, $this->schoolB, $this->classB1, '2026-02-04');

        $response = $this->actingAs($this->relation)
            ->get(route('admin.reports.index'))
            ->assertOk();

        // Judul arsip dan penjelasan baca-saja.
        $response->assertSee('Arsip Laporan');
        $response->assertSee('Riwayat Laporan');

        // Sekolah → Kelas.
        $response->assertSee('SD Arsip A');
        $response->assertSee('SD Arsip B');
        $response->assertSee('Grade 1A');
        $response->assertSee('Grade 2A');
        $response->assertSee('Grade 1B');

        // Lifecycle laporan dipertahankan apa adanya (tidak disalin/diringkas).
        $response->assertSee('Disetujui');
        $response->assertSee('Menunggu Review');
    }

    public function test_archive_shows_latest_reports_first(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-01-05');
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-06-05');

        $content = $this->actingAs($this->relation)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($content, '05 Jun 2026'),
            strpos($content, '05 Jan 2026'),
            'Laporan terbaru harus tampil lebih dulu.'
        );
    }

    // ===== 2. Filter =====

    public function test_archive_filters_by_school_class_coach_and_status(): void
    {
        // Tanggal laporan dipakai sebagai penanda baris: nama sekolah/kelas juga
        // muncul pada dropdown filter, jadi tidak bisa membuktikan isi tabel.
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02', 'approved');
        $this->report($this->coachA, $this->schoolA, $this->classA2, '2026-02-05', 'rejected');
        $this->report($this->coachB, $this->schoolB, $this->classB1, '2026-02-09', 'submitted');

        // Sekolah.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.index', ['school_id' => $this->schoolA->id]))
            ->assertOk()
            ->assertSee('02 Feb 2026')
            ->assertSee('05 Feb 2026')
            ->assertDontSee('09 Feb 2026');

        // Kelas.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.index', ['class_id' => $this->classA1->id]))
            ->assertOk()
            ->assertSee('02 Feb 2026')
            ->assertDontSee('05 Feb 2026');

        // Coach.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.index', ['coach_id' => $this->coachB->id]))
            ->assertOk()
            ->assertSee('09 Feb 2026')
            ->assertDontSee('02 Feb 2026');

        // Status.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('02 Feb 2026')
            ->assertDontSee('05 Feb 2026')
            ->assertDontSee('09 Feb 2026');

        // Rentang tanggal.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.index', ['date_from' => '2026-02-04', 'date_to' => '2026-02-06']))
            ->assertOk()
            ->assertSee('05 Feb 2026')
            ->assertDontSee('02 Feb 2026')
            ->assertDontSee('09 Feb 2026');
    }

    public function test_archive_per_page_is_whitelisted(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02');

        // Nilai yang sah diterima.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.index', ['per_page' => 50]))
            ->assertOk()
            ->assertSee('50 laporan');

        // Nilai di luar daftar jatuh ke default, bukan diteruskan ke paginator.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.index', ['per_page' => 100000]))
            ->assertOk()
            ->assertSee('20 laporan');
    }

    // ===== 3. Isolasi lintas sekolah =====

    public function test_pic_archive_only_contains_plotted_school(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02');
        $this->report($this->coachB, $this->schoolB, $this->classB1, '2026-02-03');

        $this->actingAs($this->picA)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('SD Arsip A')
            ->assertDontSee('SD Arsip B');

        // Filter sekolah di luar scope tidak membocorkan data.
        $this->actingAs($this->picA)
            ->get(route('admin.reports.index', ['school_id' => $this->schoolB->id]))
            ->assertOk()
            ->assertDontSee('SD Arsip B')
            ->assertDontSee('Grade 1B');

        // Dropdown filter pun tidak memuat sekolah/kelas/coach luar scope.
        $this->actingAs($this->picA)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertDontSee($this->coachB->name);
    }

    public function test_teacher_school_archive_only_contains_approved_reports_of_profile_school(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02', 'approved');
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-03', 'rejected');
        $this->report($this->coachB, $this->schoolB, $this->classB1, '2026-02-04', 'approved');

        // Sekolah lain tidak muncul, dan hanya laporan approved yang tampil.
        $this->actingAs($this->teacherA)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('02 Feb 2026')
            ->assertDontSee('03 Feb 2026')
            ->assertDontSee('04 Feb 2026')
            ->assertDontSee('SD Arsip B');

        // Filter status dari request diabaikan: status tetap dipaksa approved.
        $this->actingAs($this->teacherA)
            ->get(route('admin.reports.index', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee('02 Feb 2026')
            ->assertDontSee('03 Feb 2026');
    }

    public function test_finance_and_coach_cannot_open_the_archive(): void
    {
        $this->actingAs($this->finance)
            ->get(route('admin.reports.index'))
            ->assertForbidden();

        $this->actingAs($this->coach)
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }

    // ===== 4. URL langsung =====

    public function test_direct_url_to_another_school_report_is_forbidden(): void
    {
        $foreign = $this->report($this->coachB, $this->schoolB, $this->classB1, '2026-02-04', 'approved');

        $this->actingAs($this->picA)
            ->get(route('admin.reports.show', $foreign))
            ->assertForbidden();

        $this->actingAs($this->picA)
            ->get(route('admin.reports.download', $foreign))
            ->assertForbidden();

        $this->actingAs($this->finance)
            ->get(route('admin.reports.show', $foreign))
            ->assertForbidden();
    }

    public function test_teacher_school_cannot_open_unapproved_report_by_direct_url(): void
    {
        $submitted = $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02', 'submitted');

        $this->actingAs($this->teacherA)
            ->get(route('admin.reports.show', $submitted))
            ->assertForbidden();
    }

    public function test_review_actions_are_restricted_to_reviewers(): void
    {
        $submitted = $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02', 'submitted');

        $this->actingAs($this->picA)
            ->patch(route('admin.reports.approve', $submitted))
            ->assertForbidden();

        $this->assertSame('submitted', $submitted->refresh()->status);

        // Relation tetap boleh menyetujui — arsip hanya baca-saja bagi yang
        // tidak punya hak review.
        $this->actingAs($this->relation)
            ->patch(route('admin.reports.approve', $submitted))
            ->assertRedirect();

        $this->assertSame('approved', $submitted->refresh()->status);
    }

    // ===== 5. Arsip bukan penyimpanan kedua =====

    public function test_archive_does_not_duplicate_report_rows(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02', 'approved');

        $this->actingAs($this->relation)->get(route('admin.reports.index'))->assertOk();

        // Membuka arsip tidak menambah baris laporan apa pun.
        $this->assertSame(1, Report::count());
    }

    public function test_archive_lists_fewer_reports_than_total_when_out_of_scope(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02', 'approved');
        $this->report($this->coachB, $this->schoolB, $this->classB1, '2026-02-03', 'approved');

        // Arsip PIC hanya memuat satu laporan dari dua yang ada — bukti bahwa
        // yang tampil adalah query ter-scope, bukan salinan tabel.
        $this->actingAs($this->picA)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('1 laporan');

        $this->actingAs($this->relation)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('SD Arsip A')
            ->assertSee('SD Arsip B');
    }

    // ===== 6. REVIEW vs ARSIP (aturan final 2026-09-28) =====

    public function test_review_queue_lists_only_reports_awaiting_action(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02', 'approved');
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-03', 'submitted');
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-04', 'rejected');

        $response = $this->actingAs($this->relation)
            ->get(route('admin.reports.review'))
            ->assertOk();

        $response->assertSee('Review Laporan');
        $response->assertSee('Antrean Review');

        // Antrean = menunggu keputusan + menunggu koreksi coach.
        $response->assertSee('03 Feb 2026');
        $response->assertSee('04 Feb 2026');

        // Yang sudah disetujui bukan pekerjaan reviewer — ada di arsip.
        $response->assertDontSee('02 Feb 2026');
    }

    public function test_archive_keeps_the_full_history_while_review_holds_only_the_queue(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-02', 'approved');
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-03', 'submitted');

        // Arsip memuat SEMUA status, termasuk yang sudah selesai.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('02 Feb 2026')
            ->assertSee('03 Feb 2026');

        // Review hanya memuat antrean.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.review'))
            ->assertOk()
            ->assertSee('03 Feb 2026')
            ->assertDontSee('02 Feb 2026');

        // Dua halaman, satu tabel: membuka keduanya tidak menggandakan baris.
        $this->assertSame(2, Report::count());
    }

    public function test_archive_page_exposes_no_review_actions(): void
    {
        $submitted = $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-03', 'submitted');

        // Arsip baca-saja: tidak ada form setujui/tolak di halaman riwayat.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertDontSee(route('admin.reports.approve', $submitted), false)
            ->assertDontSee(route('admin.reports.reject', $submitted), false);

        // Aksi review ada di halaman antreannya.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.review'))
            ->assertOk()
            ->assertSee(route('admin.reports.approve', $submitted), false)
            ->assertSee(route('admin.reports.reject', $submitted), false);
    }

    public function test_reviewer_can_decide_straight_from_the_queue(): void
    {
        $submitted = $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-03', 'submitted');

        $this->actingAs($this->relation)
            ->patch(route('admin.reports.approve', $submitted))
            ->assertRedirect();

        $this->assertSame('approved', $submitted->refresh()->status);

        // Sudah selesai → keluar dari antrean, tetap ada di arsip.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.review'))
            ->assertOk()
            ->assertDontSee('03 Feb 2026');

        $this->actingAs($this->relation)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('03 Feb 2026');
    }

    public function test_review_queue_is_restricted_to_reviewers(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-03', 'submitted');

        // PIC tetap boleh membuka arsip sekolahnya, tetapi antrean review
        // adalah wewenang reviewer (Relation/SuperAdmin).
        $this->actingAs($this->picA)
            ->get(route('admin.reports.index'))
            ->assertOk();

        $this->actingAs($this->picA)
            ->get(route('admin.reports.review'))
            ->assertForbidden();

        $this->actingAs($this->coach)
            ->get(route('admin.reports.review'))
            ->assertForbidden();

        $this->actingAs($this->superadmin)
            ->get(route('admin.reports.review'))
            ->assertOk();
    }

    public function test_review_queue_filters_stay_inside_school_scope(): void
    {
        $this->report($this->coachA, $this->schoolA, $this->classA1, '2026-02-03', 'submitted');
        $this->report($this->coachB, $this->schoolB, $this->classB1, '2026-02-09', 'submitted');

        $this->actingAs($this->relation)
            ->get(route('admin.reports.review', ['school_id' => $this->schoolA->id]))
            ->assertOk()
            ->assertSee('03 Feb 2026')
            ->assertDontSee('09 Feb 2026');

        // Filter status mempersempit antrean, tidak melebarkannya.
        $this->actingAs($this->relation)
            ->get(route('admin.reports.review', ['status' => 'rejected']))
            ->assertOk()
            ->assertDontSee('03 Feb 2026')
            ->assertDontSee('09 Feb 2026');
    }
}
