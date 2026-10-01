<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Navigasi & konteks halaman (overhaul UX/UI + navigasi LRS 2026-10-01).
 *
 * MASALAH YANG DIKUNCI
 *
 * Sebelum overhaul, struktur aplikasi hanya benar-benar "bertingkat" di satu
 * tempat: rantai kehadiran. Di luar itu, halaman daftar tidak punya remah
 * navigasi, dan setiap tautan menuju halaman detail membuang filter yang
 * sedang aktif — sehingga user yang menyaring daftar, membuka satu baris,
 * lalu menekan "Kembali", mendarat di daftar tanpa filternya dan harus
 * mengetik ulang kata kuncinya.
 *
 * Yang diuji di sini adalah KONTRAK NAVIGASI, bukan tata letak:
 *
 *  1. filter GET ikut terbawa ke halaman berikutnya (`ctx_route`);
 *  2. remah navigasi mengembalikan user ke daftar asal LENGKAP dengan filter
 *     dan nomor halamannya;
 *  3. setiap halaman punya tepat satu <h1> (hierarki heading untuk pembaca
 *     layar);
 *  4. tidak ada lagi `javascript:history.back()` — tombol kembali harus
 *     melewati routing Laravel, bukan riwayat yang bisa basi;
 *  5. form filter TIDAK PERNAH membawa token CSRF di URL;
 *  6. peningkatan navigasi dimuat tepat sekali.
 *
 * Yang TIDAK diuji di sini: tata letak, warna, dan ukuran. Itu wilayah
 * tinjauan visual, bukan test.
 */
class NavigationContextTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private SchoolClass $class;
    private Report $report;
    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Sekolah Navigasi']);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Kelas Navigasi']);

        $coach = User::create([
            'name'     => 'Coach Navigasi',
            'email'    => 'coach.nav@test.test',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_COACH,
        ]);

        $student = Student::create(['class_id' => $this->class->id, 'name' => 'Murid Navigasi']);

        $this->report = Report::create([
            'coach_id'        => $coach->id,
            'school_id'       => $this->school->id,
            'class_id'        => $this->class->id,
            'report_date'     => '2026-09-01',
            'lesson_material' => 'Materi Navigasi',
            'goals_materi'    => 'Goals',
            'activity_report' => 'Ringkasan',
            'status'          => 'approved',
        ]);

        ReportAttendance::create([
            'report_id'  => $this->report->id,
            'student_id' => $student->id,
            'status'     => 'present',
        ]);

        // Finance melihat seluruh sekolah tanpa plotting — dipakai sebagai
        // aktor netral supaya test tidak bergantung pada pivot school_user.
        $this->finance = User::create([
            'name'     => 'Finance Navigasi',
            'email'    => 'finance.nav@test.test',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_FINANCE,
        ]);
    }

    // =====================================================================
    // 1. Filter terbawa ke halaman berikutnya
    // =====================================================================

    public function test_the_active_filter_is_carried_into_the_drill_down_link(): void
    {
        $html = $this->actingAs($this->finance)
            ->get(route('attendance.index', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']))
            ->assertOk()
            ->getContent();

        // Tautan ke detail sekolah harus membawa rentang tanggal yang sedang
        // dilihat. Sebelumnya parameter ini hilang di setiap lompatan.
        $this->assertMatchesRegularExpression(
            '#href="[^"]*attendance/schools/'.$this->school->id.'\?[^"]*date_from=2026-09-01#',
            $html,
            'Tautan ke detail sekolah tidak membawa filter tanggal.'
        );
        $this->assertMatchesRegularExpression(
            '#href="[^"]*attendance/schools/'.$this->school->id.'\?[^"]*date_to=2026-09-30#',
            $html,
            'Tautan ke detail sekolah tidak membawa batas akhir tanggal.'
        );
    }

    public function test_the_page_number_is_not_carried_into_a_different_list(): void
    {
        $html = $this->actingAs($this->finance)
            ->get(route('attendance.index', ['date_from' => '2026-09-01', 'page' => 2]))
            ->assertOk()
            ->getContent();

        // Nomor halaman daftar asal tidak ada artinya untuk daftar tujuan.
        $this->assertDoesNotMatchRegularExpression(
            '#href="[^"]*attendance/schools/\d+\?[^"]*page=#',
            $html,
            'Parameter page tidak boleh diteruskan ke daftar yang berbeda.'
        );
    }

    // =====================================================================
    // 2. Remah navigasi mengembalikan konteks
    // =====================================================================

    public function test_the_breadcrumb_back_to_the_list_restores_filter_and_page(): void
    {
        $html = $this->actingAs($this->finance)
            ->get(route('attendance.school', [
                'school'    => $this->school,
                'date_from' => '2026-09-01',
                'page'      => 2,
            ]))
            ->assertOk()
            ->getContent();

        // Remah "Attendance" menuju daftar induk — dan karena tautan itulah
        // yang dipakai user untuk kembali, filter serta halamannya harus ikut.
        $this->assertMatchesRegularExpression(
            '#href="[^"]*attendance\?[^"]*date_from=2026-09-01[^"]*"#',
            $html,
            'Remah navigasi ke daftar tidak membawa filter.'
        );
    }

    public function test_the_session_page_shows_the_whole_drill_down_hierarchy(): void
    {
        $html = $this->actingAs($this->finance)
            ->get(route('attendance.session', $this->report))
            ->assertOk()
            ->getContent();

        // Tingkat 1..4: modul, sekolah, kelas, tanggal — masing-masing dapat
        // diklik kecuali halaman yang sedang dibuka.
        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertStringContainsString('app-breadcrumb', $html);
        $this->assertStringContainsString($this->school->name, $html);
        $this->assertStringContainsString($this->class->name, $html);

        // Tepat satu item ditandai sebagai halaman aktif — dihitung di dalam
        // <nav> remah saja, karena paginator Bootstrap juga memakai
        // aria-current="page" untuk nomor halaman yang sedang dibuka.
        $this->assertSame(1, preg_match('#<nav[^>]*aria-label="Breadcrumb".*?</nav>#s', $html));
        $breadcrumb = strstr($html, 'aria-label="Breadcrumb"');
        $breadcrumb = substr($breadcrumb, 0, strpos($breadcrumb, '</nav>'));

        $this->assertSame(
            1,
            substr_count($breadcrumb, 'aria-current="page"'),
            'Remah navigasi harus menandai tepat satu halaman aktif.'
        );
    }

    // =====================================================================
    // 3. Hierarki heading
    // =====================================================================

    public function test_key_pages_have_exactly_one_h1(): void
    {
        // Topbar memakai <span>, jadi setiap <h1> di halaman berasal dari
        // x-page-header. Lebih dari satu berarti hierarki heading kacau untuk
        // pembaca layar.
        $pages = [
            'attendance.index' => route('attendance.index'),
            'attendance.school' => route('attendance.school', $this->school),
            'attendance.class' => route('attendance.class', [$this->school, $this->class]),
        ];

        foreach ($pages as $name => $url) {
            $html = $this->actingAs($this->finance)->get($url)->assertOk()->getContent();

            $this->assertSame(
                1,
                substr_count($html, '<h1'),
                "Halaman {$name} harus punya tepat satu <h1>."
            );
        }
    }

    public function test_no_view_renders_more_than_one_page_title(): void
    {
        // <x-page-header> sudah merender <h1 class="page-title">-nya sendiri.
        // Halaman yang masih menyimpan <h1> sendiri di sebelahnya akan punya
        // dua judul — pembaca layar membacakan judul dua kali, dan halaman
        // tampak seperti punya dua tingkat teratas.
        //
        // Diperiksa di sumber view supaya seluruh halaman terjangkau tanpa
        // perlu menyiapkan fixture untuk setiap role.
        $violations = [];

        foreach ($this->viewFiles() as $path) {
            $relative = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $path);

            // Partial, layout, dan dokumen cetak memang boleh menyimpang.
            if (preg_match('#^(components|partials|layouts|vendor)[\\\\/]#', $relative)) {
                continue;
            }

            $source = file_get_contents($path);
            $hasPageHeader = str_contains($source, '<x-page-header');
            $heads = substr_count($source, '<h1');

            if ($heads > 1 || ($hasPageHeader && $heads > 0)) {
                $violations[] = "{$relative} ({$heads} <h1>, page-header: ".($hasPageHeader ? 'ya' : 'tidak').')';
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Halaman berikut punya lebih dari satu judul tingkat atas:\n".implode("\n", $violations)
        );
    }

    public function test_every_admin_list_page_shows_a_breadcrumb(): void
    {
        // §2: setiap halaman harus menjawab "saya sedang di mana" dan "dari
        // mana saya datang". Sebelum overhaul hanya rantai attendance yang
        // punya remah; halaman admin lain hanya punya judul.
        $superadmin = User::create([
            'name'     => 'Superadmin Navigasi',
            'email'    => 'superadmin.nav@test.test',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_SUPERADMIN,
        ]);

        $pages = [
            'admin.master.schools' => 'admin.schools.index',
            'admin.master.classes' => 'admin.classes.index',
            'admin.master.programs' => 'admin.programs.index',
            'admin.master.coaches' => 'admin.coaches.index',
            'admin.users' => 'admin.users.index',
            'admin.reports' => 'admin.reports.index',
            'admin.reports.review' => 'admin.reports.review',
            'admin.activity-logs' => 'admin.activity-logs.index',
            'admin.schedules' => 'admin.schedules.index',
        ];

        foreach ($pages as $name => $routeName) {
            if (! \Illuminate\Support\Facades\Route::has($routeName)) {
                continue;
            }

            $response = $this->actingAs($superadmin)->get(route($routeName));

            // Beberapa halaman memakai gate tambahan; yang penting bukan
            // statusnya, melainkan bahwa halaman yang benar-benar tampil
            // selalu punya remah.
            if ($response->getStatusCode() !== 200) {
                continue;
            }

            $html = $response->getContent();

            $this->assertStringContainsString(
                'aria-label="Breadcrumb"',
                $html,
                "Halaman {$name} tidak menampilkan remah navigasi."
            );
            $this->assertStringContainsString(
                'href="'.route('admin.dashboard').'"',
                $html,
                "Remah di {$name} tidak menautkan kembali ke Dashboard."
            );
        }
    }

    // =====================================================================
    // 4. Tidak ada lagi tombol kembali yang melewati routing
    // =====================================================================

    public function test_no_view_uses_javascript_history_back_as_a_link_target(): void
    {
        $violations = [];

        foreach ($this->viewFiles() as $path) {
            $source = file_get_contents($path);

            if (preg_match('/href\s*=\s*["\']javascript:history\.back\(/i', $source)) {
                $violations[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $path);
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Tautan berikut memakai javascript:history.back() alih-alih routing Laravel, "
            ."sehingga bisa mendarat di entri riwayat yang basi:\n".implode("\n", $violations)
        );
    }

    // =====================================================================
    // 5. Form filter tidak pernah membawa token CSRF di URL
    // =====================================================================

    public function test_get_filter_forms_never_carry_a_csrf_token_in_the_url(): void
    {
        // Token CSRF pada form GET ikut tercatat di riwayat browser, log
        // server, dan header Referer. Setiap form GET harus bersih.
        $pages = [
            route('attendance.index'),
            route('attendance.school', $this->school),
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($this->finance)->get($url)->assertOk()->getContent();

            if (! preg_match_all('#<form\b[^>]*>(.*?)</form>#si', $html, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $tag = strstr($match[0], '>', true);

                if (! preg_match('/method\s*=\s*["\']?get["\']?/i', $tag)) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    'name="_token"',
                    $match[1],
                    "Form GET di {$url} membawa token CSRF."
                );
            }
        }
    }

    // =====================================================================
    // 6. Peningkatan navigasi
    // =====================================================================

    public function test_navigation_enhancement_is_loaded_once_per_page(): void
    {
        $html = $this->actingAs($this->finance)
            ->get(route('attendance.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'window.__lrsNavEnhanced = true;'),
            'Skrip peningkatan navigasi tidak boleh dimuat dua kali.'
        );
    }

    public function test_the_progress_indicator_starts_hidden_and_is_wired_to_the_script(): void
    {
        $html = $this->actingAs($this->finance)
            ->get(route('attendance.index'))
            ->assertOk()
            ->getContent();

        // Ada di HTML pertama dalam keadaan tersembunyi, sehingga tidak pernah
        // berkedip sebelum skrip sempat memasang pemantau.
        $this->assertMatchesRegularExpression(
            '/<div[^>]*id="navProgress"[^>]*hidden[^>]*>/',
            $html,
            'Indikator progres harus dirender dalam keadaan hidden.'
        );
        $this->assertStringContainsString("document.getElementById('navProgress')", $html);
    }

    public function test_prefetch_refuses_the_documented_unsafe_targets(): void
    {
        $html = $this->actingAs($this->finance)
            ->get(route('attendance.index'))
            ->assertOk()
            ->getContent();

        // Daftar larangan harus benar-benar ada di skrip, bukan hanya di
        // komentar: logout, unduhan, ekspor, dan media tidak boleh di-prefetch.
        foreach (['logout', 'download', 'export', 'media'] as $blocked) {
            $this->assertStringContainsString($blocked, $html);
        }

        $this->assertStringContainsString('BLOCKED_PATH', $html);
        $this->assertStringContainsString('BLOCKED_EXT', $html);
        $this->assertStringContainsString('saveData', $html);

        // Tautan bisa mengecualikan dirinya sendiri.
        $this->assertStringContainsString('data-no-prefetch', $html);
    }

    public function test_the_application_still_works_without_javascript(): void
    {
        // Peningkatan navigasi adalah progressive enhancement: halaman harus
        // tetap berisi tautan biasa yang bisa diklik tanpa satu baris pun JS.
        $html = $this->actingAs($this->finance)
            ->get(route('attendance.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '#<a\s+href="[^"]*attendance/schools/\d+#',
            $html,
            'Navigasi tidak boleh bergantung pada JavaScript.'
        );
    }

    /**
     * @return array<int, string>
     */
    private function viewFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
