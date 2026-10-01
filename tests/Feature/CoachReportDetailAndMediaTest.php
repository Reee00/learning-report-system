<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\Report;
use App\Models\ReportMedia;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingSchedule;
use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perubahan 2026-09-29 (4 item):
 *
 * 1. PDF report: "File Video" menjadi tautan "Lihat Video" ke HALAMAN DETAIL
 *    laporan — bukan URL file video langsung.
 * 2. Detail report role Coach: nilai "Materi Pelajaran" yang tersimpan dari
 *    form laporan ditampilkan kembali apa adanya (tidak diambil dari jadwal,
 *    kelas, atau program).
 * 3. Tombol Download pada setiap foto dokumentasi report, memakai route media
 *    terotorisasi yang sama (file privat tidak pernah menjadi publik).
 * 4. Accident Notes: pengingat pribadi coach — bukan bagian dari notification
 *    center, dan hanya milik coach sendiri.
 *
 * Lanjutan 2026-09-29 (hasil QA):
 * - "Materi yang Dipelajari" juga tampil di daftar "Riwayat Laporan",
 *   sumbernya tetap reports.lesson_material (dipakai bersama halaman detail).
 * - Accident Notes pindah ke menu sidebar-nya sendiri, keluar dari daftar
 *   "Laporan Saya"; datanya tidak disentuh, hanya penyajiannya yang dipindah.
 */
class CoachReportDetailAndMediaTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-03-02';

    private School $school;
    private SchoolClass $class;
    private Program $program;
    private User $coach;
    private User $additional;
    private User $outsider;
    private User $relation;
    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('report_media');

        $this->school = School::create(['name' => 'SD Nusantara']);
        $this->class = SchoolClass::create([
            'school_id' => $this->school->id,
            'name' => 'Grade 6A',
        ]);
        $this->program = Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);

        Student::create(['class_id' => $this->class->id, 'name' => 'Murid Satu']);

        $this->coach = $this->makeUser(User::ROLE_COACH, 'coach.rina');
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->class->id]);

        // Coach pendamping: hanya terlibat lewat SESI, tanpa coach_classes.
        $this->additional = $this->makeUser(User::ROLE_COACH, 'coach.dimas');

        $this->outsider = $this->makeUser(User::ROLE_COACH, 'coach.luar');

        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation');

        $this->pic = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.sekolah');
        $this->pic->schools()->attach($this->school->id);
    }

    private function makeUser(string $role, string $slug): User
    {
        return User::create([
            'name'     => ucwords(str_replace('.', ' ', $slug)),
            'email'    => $slug . '@test.test',
            'password' => Hash::make('password'),
            'role'     => $role,
        ]);
    }

    /**
     * Sesi mengajar dengan nomor pertemuan (meeting_number) eksplisit.
     *
     * @param  array<int, User>  $additionalCoaches
     */
    private function teachSession(array $additionalCoaches = [], int $meetingNumber = 1): TeachingSchedule
    {
        $session = TeachingSchedule::create([
            'school_id'      => $this->school->id,
            'class_id'       => $this->class->id,
            'program_id'     => $this->program->id,
            'coach_id'       => $this->coach->id,
            'session_date'   => self::DATE,
            'start_time'     => '08:00',
            'end_time'       => '09:30',
            'student_count'  => 12,
            'meeting_number' => $meetingNumber,
            'topic'          => 'Topik dari Jadwal',
            'is_active'      => true,
        ]);

        if ($additionalCoaches !== []) {
            $session->additionalCoaches()->sync(array_map(fn (User $u) => $u->id, $additionalCoaches));
        }

        return $session;
    }

    /**
     * Laporan dibuat lewat jalur resmi (form coach), sehingga nilai yang diuji
     * benar-benar nilai yang tersimpan dari input form.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function submitReport(array $overrides = []): Report
    {
        $student = Student::where('class_id', $this->class->id)->firstOrFail();

        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), array_merge([
                'class_id'        => $this->class->id,
                'report_date'     => self::DATE,
                'lesson_material' => 'Introduction',
                'goals_materi'    => 'Goals sesi',
                'activity_report' => 'Ringkasan kegiatan',
                'notes'           => null,
                'attendance'      => [$student->id => 'present'],
            ], $overrides))
            ->assertSessionHasNoErrors();

        return Report::latest('id')->firstOrFail();
    }

    private function addMedia(Report $report, string $type, string $name): ReportMedia
    {
        $file = match ($type) {
            'video' => UploadedFile::fake()->create($name, 512, 'video/mp4'),
            default => UploadedFile::fake()->image($name, 800, 600),
        };

        return app(MediaStorageService::class)->store($report, $file, $type);
    }

    private function approve(Report $report): Report
    {
        $report->update([
            'status'      => 'approved',
            'approved_by' => $this->relation->id,
            'approved_at' => now(),
        ]);

        return $report->refresh();
    }

    /**
     * Kembali ke kondisi tamu. `actingAs()` bertahan sepanjang satu test,
     * jadi guard-nya harus dilepas dulu sebelum menguji akses tanpa login.
     */
    private function asGuest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    // =====================================================================
    // 1. PDF REPORT — "Lihat Video" mengarah ke halaman detail laporan
    // =====================================================================

    public function test_pdf_video_links_to_report_detail_page_not_the_video_file(): void
    {
        $report = $this->approve($this->submitReport());
        $video = $this->addMedia($report, 'video', 'penampilan-akhir.mp4');

        $html = $this->actingAs($this->relation)
            ->get(route('admin.reports.download', $report))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Lihat Video', $html);
        $this->assertStringContainsString(route('admin.reports.show', $report), $html);

        // Bukan URL file video langsung, dan video tetap tidak di-embed.
        $this->assertStringNotContainsString($video->url(), $html);
        $this->assertStringNotContainsString('<video', $html);
    }

    public function test_pdf_opened_by_coach_links_to_the_coach_detail_page(): void
    {
        $report = $this->approve($this->submitReport());
        $this->addMedia($report, 'video', 'penampilan-akhir.mp4');

        $html = $this->actingAs($this->coach)
            ->get(route('coach.reports.download', $report))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Lihat Video', $html);
        $this->assertStringContainsString(route('coach.reports.show', $report), $html);
    }

    public function test_pdf_opened_by_pic_links_to_the_pic_detail_page(): void
    {
        $report = $this->approve($this->submitReport());
        $this->addMedia($report, 'video', 'penampilan-akhir.mp4');

        $html = $this->actingAs($this->pic)
            ->get(route('pic.reports.download', $report))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Lihat Video', $html);
        $this->assertStringContainsString(route('pic.reports.show', $report), $html);
    }

    public function test_pdf_downloaded_by_coach_through_the_admin_endpoint_links_to_the_coach_detail_page(): void
    {
        // Coach boleh memakai pintu unduhan admin untuk laporannya sendiri.
        // Konteksnya tetap coach: tautan "Lihat Video" dan tombol "Kembali"
        // harus menuju halaman coach yang memang bisa ia buka, bukan halaman
        // admin yang akan menolaknya dengan 403.
        $report = $this->approve($this->submitReport());
        $this->addMedia($report, 'video', 'penampilan-akhir.mp4');

        $html = $this->actingAs($this->coach)
            ->get(route('admin.reports.download', $report))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('coach.reports.show', $report), $html);
        $this->assertStringContainsString(route('coach.reports.index'), $html);
        $this->assertStringNotContainsString(route('admin.reports.show', $report), $html);
    }

    public function test_pdf_without_video_still_renders_safely_for_print(): void
    {
        $report = $this->approve($this->submitReport());
        $photo = $this->addMedia($report, 'photo', 'sesi-peraga.jpg');

        $html = $this->actingAs($this->relation)
            ->get(route('admin.reports.download', $report))
            ->assertOk()
            ->getContent();

        // Foto tetap inline lewat route media terotorisasi.
        $this->assertStringContainsString($photo->url(), $html);
        $this->assertStringNotContainsString('Lihat Video', $html);
    }

    // =====================================================================
    // 2. DETAIL REPORT COACH — Materi Pelajaran dari form laporan
    // =====================================================================

    public function test_coach_detail_shows_lesson_material_saved_from_the_form(): void
    {
        $report = $this->submitReport(['lesson_material' => 'Introduction']);

        $this->assertSame('Introduction', $report->lesson_material);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee('Materi Pelajaran')
            ->assertSee('Introduction');
    }

    public function test_coach_detail_shows_meeting_label_from_the_linked_session(): void
    {
        $this->teachSession(meetingNumber: 1);
        $report = $this->submitReport(['lesson_material' => 'Introduction']);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee('Pertemuan 1 — Introduction');
    }

    public function test_lesson_material_comes_from_the_report_not_from_the_schedule(): void
    {
        // Sesi punya topiknya sendiri. Materi laporan HARUS tetap nilai yang
        // diisi coach, bukan topik jadwal.
        $this->teachSession(meetingNumber: 3);

        $report = $this->submitReport(['lesson_material' => 'Pecahan Senilai']);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee('Pertemuan 3 — Pecahan Senilai')
            ->assertDontSee('Topik dari Jadwal');
    }

    public function test_lesson_material_is_updated_when_the_coach_resubmits(): void
    {
        $this->teachSession();
        $report = $this->submitReport(['lesson_material' => 'Materi Awal']);

        $report->update(['status' => 'rejected', 'admin_notes' => 'Perbaiki materi.']);

        $this->actingAs($this->coach)
            ->put(route('coach.reports.update', $report), [
                'report_date'     => self::DATE,
                'lesson_material' => 'Materi Revisi',
                'goals_materi'    => 'Goals sesi',
                'activity_report' => 'Ringkasan kegiatan',
                'attendance'      => [Student::where('class_id', $this->class->id)->firstOrFail()->id => 'present'],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee('Pertemuan 1 — Materi Revisi')
            ->assertDontSee('Materi Awal');
    }

    public function test_coach_can_open_detail_of_own_report_in_any_status(): void
    {
        $report = $this->submitReport();

        // submitted
        $this->actingAs($this->coach)->get(route('coach.reports.show', $report))->assertOk();

        // rejected
        $report->update(['status' => 'rejected', 'admin_notes' => 'Perlu revisi.']);
        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee('Perlu revisi.')
            ->assertSee(route('coach.reports.edit', $report), false);

        // approved
        $this->approve($report);
        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee(route('coach.reports.download', $report), false);
    }

    public function test_additional_coach_can_open_detail_of_the_session_report(): void
    {
        $this->teachSession([$this->additional]);
        $report = $this->submitReport();

        $this->actingAs($this->additional)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee('Materi Pelajaran');
    }

    public function test_unrelated_coach_cannot_open_the_report_detail(): void
    {
        $report = $this->submitReport();

        $this->actingAs($this->outsider)
            ->get(route('coach.reports.show', $report))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_for_report_detail(): void
    {
        $report = $this->submitReport();

        $this->asGuest();

        $this->get(route('coach.reports.show', $report))->assertRedirect(route('login'));
    }

    public function test_coach_report_list_links_to_the_detail_page(): void
    {
        $report = $this->submitReport();

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee(route('coach.reports.show', $report), false);
    }

    // ---------------------------------------------------------------------
    // 2b. RIWAYAT LAPORAN COACH — kolom "Materi yang Dipelajari"
    // ---------------------------------------------------------------------

    public function test_report_history_shows_material_with_meeting_label_from_the_linked_session(): void
    {
        $this->teachSession(meetingNumber: 1);
        $this->submitReport(['lesson_material' => 'Introduction']);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Materi yang Dipelajari')
            ->assertSee('Pertemuan 1 — Introduction');
    }

    public function test_report_history_shows_the_stored_material_verbatim(): void
    {
        $this->teachSession(meetingNumber: 1);
        $report = $this->submitReport(['lesson_material' => 'Pertemuan 1 — Introduction']);

        // Nilai tersimpan ditampilkan apa adanya, tidak dilabeli dua kali.
        $this->assertSame('Pertemuan 1 — Introduction', $report->lesson_material);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Pertemuan 1 — Introduction')
            ->assertDontSee('Pertemuan 1 — Pertemuan 1');
    }

    public function test_report_history_material_comes_from_the_report_not_the_schedule(): void
    {
        $this->teachSession(meetingNumber: 3);
        $this->submitReport(['lesson_material' => 'Pecahan Senilai']);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Pertemuan 3 — Pecahan Senilai')
            ->assertDontSee('Topik dari Jadwal');
    }

    public function test_report_history_shows_material_for_every_status(): void
    {
        $this->teachSession();

        // Laporan pertama menempel ke sesi (tanggalnya sama) sehingga dapat
        // label Pertemuan; dua lainnya berdiri sendiri, jadi materinya tampil
        // apa adanya. Yang diuji: materi benar untuk KETIGA status.
        $submitted = $this->submitReport(['lesson_material' => 'Materi Submitted']);
        $this->assertSame('submitted', $submitted->status);

        $rejected = $this->submitReport([
            'report_date'     => '2026-03-04',
            'lesson_material' => 'Materi Rejected',
        ]);
        $rejected->update(['status' => 'rejected', 'admin_notes' => 'Perbaiki.']);

        $approved = $this->submitReport([
            'report_date'     => '2026-03-05',
            'lesson_material' => 'Materi Approved',
        ]);
        $this->approve($approved);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Pertemuan 1 — Materi Submitted')
            ->assertSee('Materi Rejected')
            ->assertSee('Materi Approved');
    }

    public function test_report_history_material_is_updated_after_resubmit(): void
    {
        $this->teachSession();
        $report = $this->submitReport(['lesson_material' => 'Materi Awal']);

        $report->update(['status' => 'rejected', 'admin_notes' => 'Perbaiki materi.']);

        $this->actingAs($this->coach)
            ->put(route('coach.reports.update', $report), [
                'report_date'     => self::DATE,
                'lesson_material' => 'Materi Revisi',
                'goals_materi'    => 'Goals sesi',
                'activity_report' => 'Ringkasan kegiatan',
                'attendance'      => [Student::where('class_id', $this->class->id)->firstOrFail()->id => 'present'],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Pertemuan 1 — Materi Revisi')
            ->assertDontSee('Materi Awal');
    }

    public function test_report_history_material_matches_the_detail_page(): void
    {
        $this->teachSession(meetingNumber: 2);
        $report = $this->submitReport(['lesson_material' => 'Bangun Datar']);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Pertemuan 2 — Bangun Datar');

        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee('Pertemuan 2 — Bangun Datar');
    }

    // =====================================================================
    // 3. DOWNLOAD FOTO
    // =====================================================================

    public function test_admin_report_detail_offers_a_download_button_per_photo(): void
    {
        $report = $this->approve($this->submitReport());
        $photo = $this->addMedia($report, 'photo', 'sesi-peraga.jpg');
        $attendance = $this->addMedia($report, 'attendance', 'bukti-absensi.jpg');

        $this->actingAs($this->relation)
            ->get(route('admin.reports.show', $report))
            ->assertOk()
            // Preview tetap ada.
            ->assertSee($photo->url(), false)
            ->assertSee($attendance->url(), false)
            // Tombol download per foto.
            ->assertSee($photo->downloadUrl(), false)
            ->assertSee($attendance->downloadUrl(), false);
    }

    public function test_coach_report_detail_offers_a_download_button_per_photo(): void
    {
        $report = $this->submitReport();
        $photo = $this->addMedia($report, 'photo', 'sesi-peraga.jpg');

        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee($photo->url(), false)
            ->assertSee($photo->downloadUrl(), false);
    }

    public function test_pic_report_detail_offers_a_download_button_per_photo(): void
    {
        $report = $this->approve($this->submitReport());
        $photo = $this->addMedia($report, 'photo', 'sesi-peraga.jpg');

        $this->actingAs($this->pic)
            ->get(route('pic.reports.show', $report))
            ->assertOk()
            ->assertSee($photo->downloadUrl(), false);
    }

    public function test_photo_download_serves_the_file_as_an_attachment(): void
    {
        $report = $this->submitReport();
        $photo = $this->addMedia($report, 'photo', 'sesi-peraga.jpg');

        $response = $this->actingAs($this->coach)
            ->get($photo->downloadUrl())
            ->assertOk();

        $disposition = $response->headers->get('Content-Disposition');

        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('sesi-peraga.jpg', $disposition);
    }

    public function test_media_preview_still_serves_inline(): void
    {
        $report = $this->submitReport();
        $photo = $this->addMedia($report, 'photo', 'sesi-peraga.jpg');

        $response = $this->actingAs($this->coach)
            ->get($photo->url())
            ->assertOk();

        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_unauthorized_coach_cannot_download_media(): void
    {
        $report = $this->submitReport();
        $photo = $this->addMedia($report, 'photo', 'sesi-peraga.jpg');

        $this->actingAs($this->outsider)
            ->get($photo->downloadUrl())
            ->assertForbidden();
    }

    public function test_guest_cannot_download_media(): void
    {
        $report = $this->submitReport();
        $photo = $this->addMedia($report, 'photo', 'sesi-peraga.jpg');

        $this->asGuest();

        $this->get($photo->downloadUrl())->assertRedirect(route('login'));
    }

    public function test_video_download_is_served_as_an_attachment(): void
    {
        $report = $this->submitReport();
        $video = $this->addMedia($report, 'video', 'penampilan-akhir.mp4');

        $response = $this->actingAs($this->coach)
            ->get($video->downloadUrl())
            ->assertOk();

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    // =====================================================================
    // 4. ACCIDENT NOTES — pengingat pribadi, bukan notification center
    // =====================================================================

    public function test_accident_notes_create_no_database_notification(): void
    {
        $this->submitReport(['notes' => 'Bela terjatuh dan ditangani UKS.']);

        $this->assertDatabaseCount('notifications', 0);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk();

        // Halaman khususnya pun tidak membuat notifikasi apa pun.
        $this->actingAs($this->coach)
            ->get(route('coach.accident-notes.index'))
            ->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_coach_sees_own_accident_notes_as_personal_reminder(): void
    {
        $report = $this->submitReport(['notes' => 'Bela terjatuh dan ditangani UKS.']);

        $this->actingAs($this->coach)
            ->get(route('coach.accident-notes.index'))
            ->assertOk()
            ->assertSee('Accident Notes')
            ->assertSee('Pengingat pribadi saya')
            ->assertSee('Bela terjatuh dan ditangani UKS.')
            // Pengingat pribadi tertaut ke laporan asalnya.
            ->assertSee(route('coach.reports.show', $report), false);
    }

    public function test_accident_notes_are_no_longer_on_the_report_history_page(): void
    {
        $this->submitReport(['notes' => 'Bela terjatuh dan ditangani UKS.']);

        // "Laporan Saya" murni riwayat laporan: catatannya pindah ke menu
        // Accident Notes, bukan diduplikasi di dua tempat.
        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Riwayat Laporan')
            ->assertDontSee('Pengingat pribadi saya')
            ->assertDontSee('Bela terjatuh dan ditangani UKS.');
    }

    public function test_accident_notes_of_another_coach_are_not_shown_to_the_additional_coach(): void
    {
        $this->teachSession([$this->additional]);
        $this->submitReport(['notes' => 'Catatan kecelakaan coach utama.']);

        // Coach pendamping tetap melihat laporannya (aturan sesi bersama),
        // tetapi TIDAK melihat Accident Notes milik coach utama — baik di
        // daftar laporan maupun di halaman Accident Notes miliknya.
        $this->actingAs($this->additional)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Dibuat oleh')
            ->assertDontSee('Catatan kecelakaan coach utama.');

        $this->actingAs($this->additional)
            ->get(route('coach.accident-notes.index'))
            ->assertOk()
            ->assertDontSee('Catatan kecelakaan coach utama.')
            ->assertSee('Belum ada accident notes.');
    }

    public function test_accident_notes_page_shows_an_empty_state_when_coach_has_none(): void
    {
        $this->submitReport(['notes' => null]);

        $this->actingAs($this->coach)
            ->get(route('coach.accident-notes.index'))
            ->assertOk()
            ->assertSee('Belum ada accident notes.')
            ->assertSee('0 catatan')
            // Tidak ada kartu Accident Notes yang dirender.
            ->assertDontSee('Urgent');
    }

    public function test_accident_notes_page_lists_only_the_logged_in_coachs_notes(): void
    {
        // Catatan milik coach lain di kelas yang sama tidak boleh ikut tampil.
        $this->teachSession([$this->additional]);
        $mine = $this->submitReport(['notes' => 'Catatan milik coach utama.']);

        $otherClass = SchoolClass::create([
            'school_id' => $this->school->id,
            'name'      => 'Grade 6B',
        ]);

        $foreign = Report::create([
            'coach_id'         => $this->additional->id,
            'school_id'        => $this->school->id,
            'class_id'         => $otherClass->id,
            'report_date'      => '2026-03-03',
            'lesson_material'  => 'Materi coach lain',
            'goals_materi'     => 'Goals',
            'activity_report'  => 'Ringkasan',
            'status'           => 'submitted',
            'notes'            => 'Catatan milik coach pendamping.',
        ]);

        $this->assertNotSame($mine->id, $foreign->id);

        $this->actingAs($this->coach)
            ->get(route('coach.accident-notes.index'))
            ->assertOk()
            ->assertSee('Catatan milik coach utama.')
            ->assertDontSee('Catatan milik coach pendamping.');
    }

    public function test_accident_notes_page_is_coach_only(): void
    {
        $this->submitReport(['notes' => 'Catatan rahasia coach.']);

        // Role non-coach tidak boleh membuka halaman pribadi coach ini.
        foreach ([$this->relation, $this->pic] as $user) {
            $this->actingAs($user)
                ->get(route('coach.accident-notes.index'))
                ->assertForbidden();
        }

        // Coach lain boleh membuka halamannya sendiri, tetapi isinya kosong —
        // bukan catatan milik coach di atas.
        $this->actingAs($this->outsider)
            ->get(route('coach.accident-notes.index'))
            ->assertOk()
            ->assertSee('Belum ada accident notes.')
            ->assertDontSee('Catatan rahasia coach.');

        $this->actingAs($this->coach)
            ->get(route('coach.accident-notes.index'))
            ->assertOk()
            ->assertSee('Catatan rahasia coach.');
    }

    public function test_accident_notes_sidebar_menu_appears_for_coach_only(): void
    {
        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Accident Notes')
            ->assertSee(route('coach.accident-notes.index'), false);

        // PIC punya sidebar sendiri; menu pribadi coach tidak ikut muncul.
        $this->actingAs($this->pic)
            ->get(route('pic.dashboard'))
            ->assertOk()
            ->assertDontSee(route('coach.accident-notes.index'), false);
    }

    public function test_own_accident_note_is_visible_on_the_report_detail(): void
    {
        $report = $this->submitReport(['notes' => 'Bela terjatuh dan ditangani UKS.']);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee('Bela terjatuh dan ditangani UKS.');
    }

    public function test_accident_note_is_not_shown_to_the_additional_coach_on_detail(): void
    {
        $this->teachSession([$this->additional]);
        $report = $this->submitReport(['notes' => 'Catatan kecelakaan coach utama.']);

        $this->actingAs($this->additional)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertDontSee('Catatan kecelakaan coach utama.');
    }
}
