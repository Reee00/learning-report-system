<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Report;
use App\Models\ReportMedia;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Dokumen cetak laporan coach (review meeting LRS 2026-10-01).
 *
 * Pembacanya berusia 30–50 tahun dan mencetak ke A4 tanpa zoom: ukuran huruf,
 * hierarki judul, jarak baris, serta foto/video diperiksa di sini sebagai
 * kontrak, bukan sebagai selera. Aturan yang dikunci:
 * - teks memakai satuan pt (layar & kertas sama besar) dan minimal 11pt;
 * - foto selebar area konten, satu per baris, rasio tidak diubah;
 * - video TIDAK pernah di-embed playable — hanya blok dokumentasi + tautan
 *   "Lihat Video" yang bisa diklik menuju halaman detail laporan;
 * - tidak ada elemen yang keluar dari batas konten A4.
 */
class ReportPrintReadabilityTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private SchoolClass $class;
    private User $coach;
    private User $relation;
    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SD Cetak']);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Kelas 6A']);

        Student::create(['class_id' => $this->class->id, 'name' => 'Murid Satu']);

        $this->coach = User::create([
            'name' => 'Coach Cetak', 'email' => 'coach.cetak@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->class->id]);

        $this->relation = User::create([
            'name' => 'Relation', 'email' => 'relation.cetak@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_RELATION,
        ]);

        $this->report = Report::create([
            'coach_id'         => $this->coach->id,
            'school_id'        => $this->school->id,
            'class_id'         => $this->class->id,
            'report_date'      => '2026-09-01',
            'lesson_material'  => 'Materi',
            'goals_materi'     => 'Goals',
            'activity_report'  => 'Kegiatan',
            'status'           => 'approved',
            'approved_by'      => $this->relation->id,
            'approved_at'      => now(),
        ]);
    }

    private function addMedia(string $type, string $name): ReportMedia
    {
        return ReportMedia::create([
            'report_id'     => $this->report->id,
            'type'          => $type,
            'path'          => "reports/test/{$name}",
            'original_name' => $name,
            'disk'          => 'local',
            'file_size'     => 2048,
        ]);
    }

    private function html(): string
    {
        return $this->actingAs($this->relation)
            ->get(route('admin.reports.download', $this->report))
            ->assertOk()
            ->getContent();
    }

    // =====================================================================
    // 1. Teks panjang
    // =====================================================================

    public function test_long_text_is_printed_in_full_with_roomy_line_height(): void
    {
        $paragraph = implode("\n", array_map(
            fn (int $i) => "Baris {$i}: murid berlatih merakit robot dan mencatat hasilnya di lembar kerja.",
            range(1, 60)
        ));

        $this->report->update([
            'lesson_material' => $paragraph,
            'activity_report' => $paragraph,
        ]);

        $html = $this->html();

        // Tidak ada pemotongan: seluruh baris ikut tercetak.
        $this->assertStringContainsString('Baris 1: murid berlatih', $html);
        $this->assertStringContainsString('Baris 60: murid berlatih', $html);

        // Jarak baris lega + baris baru dipertahankan apa adanya.
        $this->assertMatchesRegularExpression('/\.text-block\s*\{[^}]*line-height:\s*1\.7[0-9]?/', $html);
        $this->assertMatchesRegularExpression('/\.text-block\s*\{[^}]*white-space:\s*pre-line/', $html);
    }

    // =====================================================================
    // 2–3. Foto
    // =====================================================================

    public function test_one_photo_is_printed_full_width_with_the_aspect_ratio_kept(): void
    {
        $photo = $this->addMedia('photo', 'kegiatan-1.jpg');

        $html = $this->html();

        $this->assertStringContainsString($photo->url(), $html);
        $this->assertStringContainsString('class="media-figure"', $html);

        // Lebar penuh area konten, tinggi mengikuti rasio asli (tidak
        // di-stretch), dan tidak pernah melebihi lebar konten.
        $this->assertMatchesRegularExpression('/\.media-figure img\s*\{[^}]*width:\s*100%/', $html);
        $this->assertMatchesRegularExpression('/\.media-figure img\s*\{[^}]*height:\s*auto/', $html);
        $this->assertMatchesRegularExpression('/\.media-figure img\s*\{[^}]*max-width:\s*100%/', $html);

        // Tidak ada lebar piksel tetap yang melebihi area konten A4.
        $this->assertDoesNotMatchRegularExpression('/\.media-figure img\s*\{[^}]*width:\s*\d{3,}px/', $html);
    }

    public function test_many_photos_each_get_their_own_full_width_block_with_spacing(): void
    {
        foreach (range(1, 6) as $i) {
            $this->addMedia('photo', "kegiatan-{$i}.jpg");
        }

        $html = $this->html();

        $this->assertSame(6, substr_count($html, 'class="media-figure"'));

        // Satu foto per baris (tanpa grid dua kolom) dan punya jarak bawah
        // supaya tidak menempel satu sama lain.
        $this->assertStringNotContainsString('grid-template-columns: repeat(2', $html);
        $this->assertMatchesRegularExpression('/\.media-figure\s*\{[^}]*margin:\s*0 0 18pt/', $html);

        // Tiap foto tidak boleh terpotong page break.
        $this->assertMatchesRegularExpression('/\.media-figure\s*\{[^}]*break-inside:\s*avoid/', $html);
    }

    // =====================================================================
    // 4 + 8. Video: blok dokumentasi, bukan embed; tautan bisa diklik
    // =====================================================================

    public function test_video_is_a_documentation_block_with_a_clickable_link(): void
    {
        $video = $this->addMedia('video', 'penampilan-akhir.mp4');

        $html = $this->html();

        // Tidak pernah di-embed playable.
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringContainsString('penampilan-akhir.mp4', $html);

        // Tautan "Lihat Video" menuju halaman detail laporan (route yang
        // meng-autorisasi media), bukan URL file langsung.
        $detailUrl = route('admin.reports.show', $this->report);
        $this->assertStringContainsString('Lihat Video', $html);
        $this->assertStringContainsString('class="video-block" href="'.$detailUrl.'"', $html);
        $this->assertStringNotContainsString($video->url(), $html);
    }

    public function test_video_block_is_full_width_and_the_fallback_url_is_readable(): void
    {
        $this->addMedia('video', 'penampilan-akhir.mp4');

        $html = $this->html();

        $this->assertMatchesRegularExpression('/\.video-block\s*\{[^}]*display:\s*block/', $html);
        $this->assertMatchesRegularExpression('/\.video-block\s*\{[^}]*break-inside:\s*avoid/', $html);

        // URL cadangan untuk salinan kertas: ukuran terbaca (>= 10pt) dan
        // boleh terpotong supaya tidak melebar keluar halaman.
        $this->assertMatchesRegularExpression('/\.video-block \.video-url\s*\{[^}]*font-size:\s*(1[0-9]|\d\d(\.\d)?)pt/', $html);
        $this->assertMatchesRegularExpression('/\.video-block \.video-url\s*\{[^}]*overflow-wrap:\s*anywhere/', $html);
    }

    // =====================================================================
    // 5–6. A4 untuk cetak, pratinjau layar yang sama bentuknya
    // =====================================================================

    public function test_page_is_set_up_for_a4_print_with_screen_preview(): void
    {
        $html = $this->html();

        // Ukuran kertas betulan, bukan skala zoom.
        $this->assertMatchesRegularExpression('/@page\s*\{[^}]*size:\s*A4/', $html);
        $this->assertMatchesRegularExpression('/@page\s*\{[^}]*margin:\s*16mm 15mm 18mm/', $html);

        // Pratinjau browser memakai lembar A4 yang sama, dan padding lembar
        // dinolkan saat dicetak supaya margin tidak dobel.
        $this->assertMatchesRegularExpression('/\.sheet\s*\{[^}]*max-width:\s*210mm/', $html);
        $this->assertStringContainsString('@media screen', $html);
        $this->assertMatchesRegularExpression('/@media print[\s\S]*?\.sheet\s*\{[^}]*padding:\s*0/', $html);
    }

    // =====================================================================
    // 7. Tidak ada elemen yang keluar dari area konten
    // =====================================================================

    public function test_content_cannot_overflow_the_a4_content_area(): void
    {
        $this->report->update([
            'lesson_material' => str_repeat('KataSangatPanjangTanpaSpasi', 30),
        ]);
        $this->addMedia('photo', 'foto-dengan-nama-berkas-yang-sangat-panjang-sekali.jpg');
        $this->addMedia('video', 'video-dengan-nama-berkas-yang-sangat-panjang-sekali.mp4');

        $html = $this->html();

        // Teks panjang tanpa spasi tetap dipatahkan, bukan melebar keluar.
        $this->assertMatchesRegularExpression('/\.text-block\s*\{[^}]*overflow-wrap:\s*break-word/', $html);
        $this->assertMatchesRegularExpression('/\.media-caption\s*\{[^}]*overflow-wrap:\s*break-word/', $html);

        // Tidak ada lebar TETAP (bukan max-width) yang lebih besar dari area
        // konten A4 (210mm - 2 x 15mm = 180mm).
        $this->assertDoesNotMatchRegularExpression('/(?<![-a-z])width:\s*(19[0-9]|2[0-9][0-9])mm/', $html);
    }

    // =====================================================================
    // 9. Huruf terbaca pada A4 normal
    // =====================================================================

    public function test_font_sizes_are_readable_without_zoom(): void
    {
        $html = $this->html();

        // Badan dokumen >= 11pt (sebelumnya 13px untuk semua hal).
        $this->assertMatchesRegularExpression('/body\s*\{[^}]*font-size:\s*11\.5pt/', $html);

        // Hierarki judul di atas badan teks, dan tetap >= 12pt.
        $this->assertMatchesRegularExpression('/\.section-title\s*\{[^}]*font-size:\s*12pt/', $html);

        // Keterangan/media tidak pernah turun ke ukuran "mikroskopis".
        foreach (['\.media-caption', '\.video-block \.video-url', '\.info-grid'] as $selector) {
            $this->assertMatchesRegularExpression(
                '/'.$selector.'\s*\{[^}]*font-size:\s*(1[0-9]|\d\d(\.\d)?)pt/',
                $html,
                "Ukuran huruf {$selector} harus >= 10pt."
            );
        }

        // Judul bagian selalu menempel pada isinya (tidak terpisah oleh
        // pergantian halaman).
        $this->assertMatchesRegularExpression('/\.section-title\s*\{[^}]*break-after:\s*avoid/', $html);
    }
}
