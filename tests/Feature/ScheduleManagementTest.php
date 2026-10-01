<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\TestCase;

/**
 * Modul manajemen jadwal mengajar (2026-09-11):
 *
 * - CRUD lengkap dengan validasi (kombinasi sekolah/kelas, assignment coach,
 *   jam, duplikat sesi, bentrok jadwal coach).
 * - Otorisasi & scope: Relation/SuperAdmin global, PIC sekolah plot-nya,
 *   Coach hanya jadwal yang melibatkannya.
 * - Multi-coach: coach utama + coach tambahan (pivot).
 * - Import Excel: template ternormalisasi + workbook master perusahaan.
 * - Kompatibilitas reminder dengan jadwal hasil CRUD/import.
 */
class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classA2;
    private SchoolClass $classB;
    private User $coach;
    private User $coachExtra;
    private User $coachB;
    private User $relation;
    private User $superadmin;
    private User $picA;
    private User $finance;
    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Test A']);
        $this->schoolB = School::create(['name' => 'SD Test B']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 1A']);
        $this->classA2 = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 2A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 1B']);

        $this->program = Program::create(['name' => 'Coding Kids', 'code' => 'COD', 'status' => 'active']);

        $this->coach = User::create([
            'name' => 'Coach Satu', 'email' => 'coach.satu@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        // Coach utama ter-assign ke dua kelas di sekolah A (untuk uji bentrok).
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->classA2->id]);

        $this->coachExtra = User::create([
            'name' => 'Coach Dua', 'email' => 'coach.dua@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coachExtra->id, 'class_id' => $this->classA->id]);

        $this->coachB = User::create([
            'name' => 'Coach B', 'email' => 'coach.b@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classB->id]);

        $this->relation = User::create([
            'name' => 'Relation Test', 'email' => 'relation@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_RELATION,
        ]);

        $this->superadmin = User::create([
            'name' => 'Super Admin', 'email' => 'superadmin@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_SUPERADMIN,
        ]);

        $this->picA = User::create([
            'name' => 'PIC A', 'email' => 'pic.a@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_SCHOOL_PIC,
        ]);
        $this->picA->schools()->attach($this->schoolA->id);

        $this->finance = User::create([
            'name' => 'Finance', 'email' => 'finance@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_FINANCE,
        ]);
    }

    /**
     * Payload store yang valid untuk sesi di sekolah A.
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'session_date' => '2026-09-15',
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'program_id' => $this->program->id,
            'coach_id' => $this->coach->id,
            'additional_coaches' => [$this->coachExtra->id],
            'start_time' => '08:00',
            'end_time' => '09:30',
            'student_count' => 12,
            'tools_dk' => 'Laptop 12 unit',
            'tools_rk' => 'Box RK A',
            'jalan_minggu_ini' => '1',
            'topic' => 'Storytelling Visual',
            'keterangan' => 'Sesi perdana',
            'departure_location' => 'BSD',
            'departure_time' => '07:15',
            'arrival_time' => '07:45',
        ], $overrides);
    }

    // ===== CRUD =====

    public function test_relation_can_create_schedule_with_full_attributes(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload())
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'sesi']);

        $schedule = TeachingSchedule::query()->firstOrFail();

        $this->assertSame($this->classA->id, $schedule->class_id);
        $this->assertSame($this->program->id, $schedule->program_id);
        $this->assertSame(12, $schedule->student_count);
        $this->assertSame('08:00', $schedule->start_time->format('H:i'));
        $this->assertSame('09:30', $schedule->end_time->format('H:i'));
        $this->assertSame('Laptop 12 unit', $schedule->tools_dk);
        $this->assertSame('Box RK A', $schedule->tools_rk);
        $this->assertTrue($schedule->jalan_minggu_ini);
        $this->assertSame('Sesi perdana', $schedule->keterangan);
        $this->assertSame('BSD', $schedule->departure_location);
        $this->assertSame('07:15', $schedule->departure_time->format('H:i'));

        // Coach tambahan tersimpan di pivot.
        $this->assertSame(
            [$this->coachExtra->id],
            $schedule->additionalCoaches->pluck('id')->all(),
        );

        // day_of_week dihitung otomatis (2026-09-15 = Selasa).
        $this->assertSame(2, $schedule->day_of_week);
    }

    public function test_relation_can_update_and_delete_schedule(): void
    {
        $schedule = TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        $this->actingAs($this->relation)
            ->put(route('admin.schedules.update', $schedule), $this->validPayload([
                'topic' => 'Topik Baru',
                'additional_coaches' => [],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'sesi']);

        $schedule->refresh();
        $this->assertSame('Topik Baru', $schedule->topic);
        $this->assertSame([], $schedule->additionalCoaches->pluck('id')->all());

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.destroy', $schedule))
            ->assertRedirect();

        $this->assertModelMissing($schedule);
    }

    public function test_update_ignores_itself_in_duplicate_check(): void
    {
        $schedule = TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        // Update tanpa mengubah slot waktu tidak boleh dianggap duplikat.
        $this->actingAs($this->relation)
            ->put(route('admin.schedules.update', $schedule), $this->validPayload())
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'sesi'])
            ->assertSessionHas('success');
    }

    // ===== Validasi =====

    public function test_duplicate_session_is_rejected(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload())
            ->assertSessionHasErrors('class_id');

        $this->assertSame(1, TeachingSchedule::count());
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'start_time' => '10:00',
                'end_time' => '09:00',
            ]))
            ->assertSessionHasErrors('end_time');
    }

    public function test_class_must_belong_to_selected_school(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'class_id' => $this->classB->id, // kelas sekolah B pada sekolah A
            ]))
            ->assertSessionHasErrors('class_id');
    }

    public function test_coach_must_be_assigned_to_class(): void
    {
        // Coach B hanya ter-assign di kelas sekolah B.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'coach_id' => $this->coachB->id,
            ]))
            ->assertSessionHasErrors('coach_id');
    }

    /**
     * Aturan bisnis 2026-09-27: coach TAMBAHAN boleh belum ter-assign permanen
     * ke kelas — ia mendapat penugasan sementara di level jadwal. Yang tetap
     * diwajibkan hanya coach utama (test_coach_must_be_assigned_to_class).
     */
    public function test_additional_coach_may_be_temporarily_assigned(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'additional_coaches' => [$this->coachB->id],
            ]))
            ->assertSessionHasNoErrors();

        $schedule = TeachingSchedule::where('class_id', $this->classA->id)->firstOrFail();

        // Tercatat di pivot jadwal…
        $this->assertTrue(
            $schedule->additionalCoaches()->whereKey($this->coachB->id)->exists(),
            'Coach tambahan harus tercatat pada pivot jadwal.'
        );

        // …tanpa membuat assignment kelas permanen.
        $this->assertFalse(
            CoachClass::where('coach_id', $this->coachB->id)
                ->where('class_id', $this->classA->id)
                ->exists(),
            'Penugasan sementara tidak boleh membuat baris coach_classes.'
        );
    }

    public function test_non_coach_account_cannot_be_additional_coach(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'additional_coaches' => [$this->finance->id],
            ]))
            ->assertSessionHasErrors('additional_coaches');
    }

    public function test_coach_time_conflict_is_rejected(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        // Sesi beririsan (09:00-10:30) dengan coach yang sama di kelas lain.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'class_id' => $this->classA2->id,
                'start_time' => '09:00',
                'end_time' => '10:30',
                'additional_coaches' => [],
            ]))
            ->assertSessionHasErrors('coach_id');
    }

    public function test_conflict_via_additional_coach_is_rejected(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        // Coach Dua (tambahan) bentrok dengan sesi 08:00-09:30.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'class_id' => $this->classA2->id,
                'coach_id' => $this->coachExtra->id,
                'additional_coaches' => [],
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]))
            ->assertSessionHasErrors('coach_id');
    }

    public function test_non_overlapping_sessions_are_allowed(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'class_id' => $this->classA2->id,
                'start_time' => '09:30',
                'end_time' => '11:00',
                'additional_coaches' => [],
            ]))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'sesi']);

        $this->assertSame(2, TeachingSchedule::count());
    }

    // ===== Otorisasi & scope =====

    public function test_coach_cannot_create_or_edit_schedules(): void
    {
        $schedule = TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        $this->actingAs($this->coach)
            ->get(route('admin.schedules.create'))
            ->assertForbidden();
        $this->actingAs($this->coach)
            ->post(route('admin.schedules.store'), $this->validPayload())
            ->assertForbidden();
        $this->actingAs($this->coach)
            ->put(route('admin.schedules.update', $schedule), $this->validPayload())
            ->assertForbidden();
        $this->actingAs($this->coach)
            ->delete(route('admin.schedules.destroy', $schedule))
            ->assertForbidden();

        $this->assertSame(1, TeachingSchedule::count());
    }

    public function test_roles_without_schedule_permission_are_locked_out(): void
    {
        // Finance tidak punya schedules.view maupun schedules.manage.
        $this->actingAs($this->finance)
            ->get(route('admin.schedules.index'))
            ->assertForbidden();
    }

    public function test_pic_can_manage_schedules_within_own_schools(): void
    {
        $this->actingAs($this->picA)
            ->post(route('admin.schedules.store'), $this->validPayload())
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'sesi']);

        $this->assertSame(1, TeachingSchedule::count());
    }

    public function test_pic_cannot_create_schedule_outside_scope(): void
    {
        $this->actingAs($this->picA)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'school_id' => $this->schoolB->id,
                'class_id' => $this->classB->id,
                'coach_id' => $this->coachB->id,
                'additional_coaches' => [],
            ]))
            ->assertForbidden();

        $this->assertSame(0, TeachingSchedule::count());
    }

    public function test_pic_cannot_edit_or_delete_other_school_schedule(): void
    {
        $schedule = TeachingSchedule::create([
            'school_id' => $this->schoolB->id,
            'class_id' => $this->classB->id,
            'coach_id' => $this->coachB->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);

        $this->actingAs($this->picA)
            ->get(route('admin.schedules.edit', $schedule))
            ->assertForbidden();
        $this->actingAs($this->picA)
            ->put(route('admin.schedules.update', $schedule), $this->validPayload())
            ->assertForbidden();
        $this->actingAs($this->picA)
            ->delete(route('admin.schedules.destroy', $schedule))
            ->assertForbidden();
    }

    public function test_superadmin_can_manage_schedules(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('admin.schedules.store'), $this->validPayload())
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'sesi']);

        $this->assertSame(1, TeachingSchedule::count());
    }

    // ===== Visibility & filter =====

    public function test_coach_sees_schedules_where_additional_coach(): void
    {
        $schedule = TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15',
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);
        $schedule->additionalCoaches()->attach($this->coachExtra->id);

        // Coach Dua adalah coach tambahan — jadwal tetap terlihat.
        // Daftar default halaman jadwal adalah POLA per hari; daftar SESI
        // tergenerate dibuka lewat tab `view=sesi`.
        $response = $this->actingAs($this->coachExtra)
            ->get(route('admin.schedules.index', ['view' => 'sesi']))
            ->assertOk();

        $response->assertSee($this->schoolA->name);
        $response->assertSee('Grade 1A');
    }

    public function test_index_filters_by_program_day_and_coach(): void
    {
        $monday = TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'program_id' => $this->program->id,
            'session_date' => '2026-09-14', // Senin
            'start_time' => '08:00',
            'end_time' => '09:30',
        ]);
        $tuesday = TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA2->id,
            'coach_id' => $this->coach->id,
            'session_date' => '2026-09-15', // Selasa
            'start_time' => '10:00',
            'end_time' => '11:30',
        ]);
        $tuesday->additionalCoaches()->attach($this->coachExtra->id);

        // Filter program: hanya sesi Senin. (`view=sesi` = daftar pertemuan
        // tergenerate, tempat filter tanggal/jam/program berlaku.)
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi', 'program_id' => $this->program->id]))
            ->assertOk()
            ->assertSee('14 Sep 2026')
            ->assertDontSee('15 Sep 2026');

        // Filter hari (Selasa = 2).
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi', 'day' => 2]))
            ->assertOk()
            ->assertSee('15 Sep 2026')
            ->assertDontSee('14 Sep 2026');

        // Filter coach: coach tambahan menemukan sesi Selasa.
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi', 'coach_id' => $this->coachExtra->id]))
            ->assertOk()
            ->assertSee('15 Sep 2026')
            ->assertDontSee('14 Sep 2026');

        // Filter rentang jam mulai.
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi', 'time_from' => '09:00']))
            ->assertOk()
            ->assertSee('15 Sep 2026')
            ->assertDontSee('14 Sep 2026');
    }

    // ===== Import: template ternormalisasi =====

    public function test_normalized_import_with_additional_coaches_and_duplicates(): void
    {
        $csv = implode("\n", [
            'tanggal,nama_sekolah,nama_kelas,email_coach,jam_mulai,jam_selesai,topik,program,email_coach_tambahan,jumlah_murid,tools_dk,tools_rk,jalan_minggu_ini,keterangan',
            '2026-09-15,SD Test A,Grade 1A,coach.satu@test.test,08:00,09:30,Prompting AI,Coding Kids,coach.dua@test.test,12,Laptop 12,Box RK A,1,Sesi perdana',
            '2026-09-15,SD Test A,Grade 1A,coach.satu@test.test,08:00,09:30,Prompting AI,Coding Kids,coach.dua@test.test,12,Laptop 12,Box RK A,1,Sesi perdana',
            '2026-09-16,SD Test A,Grade 2A,coach.satu@test.test,10:00,11:30,,,,,,,0,',
            '2026-09-16,Sekolah Tak Ada,Grade 1A,coach.satu@test.test,08:00,09:30,,,,,,,',
        ]);

        $response = $this->actingAs($this->relation)
            ->post(route('admin.schedules.import'), [
                'file' => UploadedFile::fake()->createWithContent('jadwal.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Baris 1 masuk, baris 2 duplikat dilewati, baris 3 masuk,
        // baris 4 sekolah tidak dikenal dilewati.
        $this->assertSame(2, TeachingSchedule::count());

        $first = TeachingSchedule::whereDate('session_date', '2026-09-15')->firstOrFail();
        $this->assertSame($this->program->id, $first->program_id);
        $this->assertSame(12, $first->student_count);
        $this->assertSame('Laptop 12', $first->tools_dk);
        $this->assertSame('Box RK A', $first->tools_rk);
        $this->assertTrue($first->jalan_minggu_ini);
        $this->assertSame(
            [$this->coachExtra->id],
            $first->additionalCoaches->pluck('id')->all(),
        );

        $second = TeachingSchedule::whereDate('session_date', '2026-09-16')->firstOrFail();
        $this->assertFalse($second->jalan_minggu_ini);
        $this->assertNull($second->program_id);

        $this->assertStringContainsString('2 jadwal', session('success'));
    }

    public function test_pic_import_skips_schools_outside_scope(): void
    {
        $csv = implode("\n", [
            'tanggal,nama_sekolah,nama_kelas,email_coach,jam_mulai,jam_selesai,topik',
            '2026-09-15,SD Test A,Grade 1A,coach.satu@test.test,08:00,09:30,Topik A',
            '2026-09-15,SD Test B,Grade 1B,coach.b@test.test,08:00,09:30,Topik B',
        ]);

        $this->actingAs($this->picA)
            ->post(route('admin.schedules.import'), [
                'file' => UploadedFile::fake()->createWithContent('jadwal.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Hanya jadwal sekolah plot PIC yang masuk.
        $this->assertSame(1, TeachingSchedule::count());
        $this->assertSame($this->schoolA->id, TeachingSchedule::first()->school_id);
    }

    // ===== Import: workbook master perusahaan =====

    /**
     * Bangun workbook XLSX tiruan dengan layout sheet mingguan DIGISchool:
     * header perusahaan + baris sesi + baris lanjutan coach tambahan +
     * baris sesi kedua dengan carry-down.
     */
    private function companyWorkbook(string $sheetName): UploadedFile
    {
        $path = storage_path('app/test-company-workbook.xlsx');

        $writer = new XlsxWriter();
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($sheetName);

        $writer->addRow(Row::fromValues([
            'LOKASI BERANGKAT', 'BERANGKAT', 'JAM SAMPAI', 'NAMA SEKOLAH', 'JAM KELAS',
            'PROGRAM', 'KELAS', 'JUMLAH MURID', 'TOOLS DK', 'TOOLS RK', 'Coach', '',
            'Jalan Minggu Ini ?', 'KET',
        ]));

        // Sesi 1: data lengkap. Kolom KET memuat TANGGAL MULAI pola (Senin
        // 3 Agustus 2026) plus catatan tambahan yang harus tetap tersimpan.
        $writer->addRow(Row::fromValues([
            'BSD', '13.35', '14.15', 'SD Test A', '14.45 - 15.45', 'Coding Kids',
            'Grade 1A', '3', 'Laptop 3', '', 'Mr Coach Satu', '', '1', 'Mulai tanggal 3 Agustus 2026 — kelas pagi',
        ]));

        // Baris lanjutan: coach tambahan (tanpa KELAS).
        $writer->addRow(Row::fromValues([
            '', '', '', '', '', '', '', '', '', '', '', 'Mr Coach Dua', '', '',
        ]));

        // Sesi 2: sekolah/program/keberangkatan carry-down dari merge sel.
        $writer->addRow(Row::fromValues([
            '', '', '', '', '', '', 'Grade 2A', '4', '', 'Box RK A', 'Mr Coach Satu', '', '', '',
        ]));

        $writer->close();

        return new UploadedFile(
            $path,
            'company-workbook.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    public function test_company_workbook_import_maps_sessions_and_coaches(): void
    {
        // Sheet SENIN; tanggal mulai dibaca dari kolom KET blok sekolah
        // (3 Agustus 2026 = Senin), bukan dari parameter periode global.
        $file = $this->companyWorkbook('DIGISchool - SENIN');

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Workbook = 2 baris pola sekolah A, masing-masing 20 pertemuan.
        $this->assertSame(2, \App\Models\TeachingScheduleTemplate::count());
        $this->assertSame(40, TeachingSchedule::count());

        $template = \App\Models\TeachingScheduleTemplate::where('class_id', $this->classA->id)->firstOrFail();
        $this->assertSame(1, $template->day_of_week);
        $this->assertSame(20, $template->meeting_count);
        $this->assertSame('2026-08-03', $template->start_date->toDateString());

        // Sesi pertama = tanggal mulai pada KET.
        $first = TeachingSchedule::where('class_id', $this->classA->id)
            ->orderBy('session_date')->firstOrFail();
        $this->assertSame('2026-08-03', $first->session_date->toDateString());
        $this->assertSame(1, $first->day_of_week);
        $this->assertSame(1, $first->meeting_number);
        $this->assertSame($template->id, $first->template_id);
        $this->assertSame($this->coach->id, $first->coach_id);
        $this->assertSame('14:45', $first->start_time->format('H:i'));
        $this->assertSame('15:45', $first->end_time->format('H:i'));
        $this->assertSame($this->program->id, $first->program_id);
        $this->assertSame(3, $first->student_count);
        $this->assertSame('Laptop 3', $first->tools_dk);
        $this->assertSame('BSD', $first->departure_location);
        $this->assertSame('13:35', $first->departure_time->format('H:i'));
        $this->assertSame('14:15', $first->arrival_time->format('H:i'));
        // Tanggal mulai tidak diulang di keterangan; sisa teks KET tetap ada.
        $this->assertSame('kelas pagi', $first->keterangan);
        $this->assertTrue($first->jalan_minggu_ini);

        // Sesi terakhir: pertemuan ke-20 = Senin ke-20 dari tanggal mulai.
        $last = TeachingSchedule::where('class_id', $this->classA->id)
            ->orderByDesc('session_date')->firstOrFail();
        $this->assertSame(20, $last->meeting_number);
        $this->assertSame('2026-12-14', $last->session_date->toDateString());

        // Baris lanjutan -> coach tambahan tersalin ke SEMUA sesi template.
        $this->assertSame(
            [$this->coachExtra->id],
            $first->additionalCoaches->pluck('id')->all(),
        );
        $this->assertSame(
            [$this->coachExtra->id],
            $last->additionalCoaches->pluck('id')->all(),
        );

        // Sesi 2: carry-down sekolah/program/keberangkatan + jam kelas.
        $second = TeachingSchedule::where('class_id', $this->classA2->id)->firstOrFail();
        $this->assertSame($this->schoolA->id, $second->school_id);
        $this->assertSame($this->program->id, $second->program_id);
        $this->assertSame('14:45', $second->start_time->format('H:i'));
        $this->assertSame('BSD', $second->departure_location);
        $this->assertSame('Box RK A', $second->tools_rk);
        $this->assertSame(4, $second->student_count);
        $this->assertSame([], $second->additionalCoaches->pluck('id')->all());
    }

    public function test_company_workbook_import_is_idempotent(): void
    {
        $file = $this->companyWorkbook('DIGISchool - SENIN');

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.import'), ['file' => $file])
            ->assertRedirect();

        // Import kedua kali: pola dengan (hari + sekolah + tanggal mulai) yang
        // sama dikenali sebagai pola yang sudah ada — tidak ada duplikat.
        $file2 = $this->companyWorkbook('DIGISchool - SENIN');
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.import'), ['file' => $file2])
            ->assertRedirect();

        $this->assertSame(2, \App\Models\TeachingScheduleTemplate::count());
        $this->assertSame(40, TeachingSchedule::count());
    }

    /**
     * §6: tidak ada satu tanggal mulai global untuk semua sekolah. Parameter
     * `week_start` warisan lama harus DIABAIKAN — tanggal mulai hanya datang
     * dari kolom KET tiap blok sekolah.
     */
    public function test_company_workbook_import_ignores_legacy_week_start(): void
    {
        $file = $this->companyWorkbook('DIGISchool - SENIN');

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.import'), [
                'file' => $file,
                'week_start' => '2027-01-04', // Senin, jauh dari tanggal KET
            ])
            ->assertRedirect();

        $template = \App\Models\TeachingScheduleTemplate::where('class_id', $this->classA->id)->firstOrFail();
        $this->assertSame('2026-08-03', $template->start_date->toDateString());
        $this->assertSame(
            '2026-08-03',
            $template->sessions()->orderBy('session_date')->first()->session_date->toDateString(),
        );
    }

    public function test_company_workbook_rejects_out_of_scope_school_for_pic(): void
    {
        // Sheet dengan sekolah di luar plot PIC A.
        $path = storage_path('app/test-company-workbook-pic.xlsx');
        $writer = new XlsxWriter();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('DIGISchool - SELASA');
        $writer->addRow(Row::fromValues([
            'LOKASI BERANGKAT', 'BERANGKAT', 'JAM SAMPAI', 'NAMA SEKOLAH', 'JAM KELAS',
            'PROGRAM', 'KELAS', 'JUMLAH MURID', 'TOOLS DK', 'TOOLS RK', 'Coach', '',
            'Jalan Minggu Ini ?', 'KET',
        ]));
        $writer->addRow(Row::fromValues([
            'BSD', '13.35', '14.15', 'SD Test B', '14.45 - 15.45', 'Coding Kids',
            'Grade 1B', '3', '', '', 'Mr Coach B', '', '1', 'Mulai tanggal 1 September 2026',
        ]));
        $writer->close();

        $this->actingAs($this->picA)
            ->post(route('admin.schedules.import'), [
                'file' => new UploadedFile($path, 'wb.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertRedirect();

        $this->assertSame(0, TeachingSchedule::count());
        $this->assertSame(0, \App\Models\TeachingScheduleTemplate::count());
    }

    // ===== Reminder compatibility =====

    public function test_reminder_counts_session_created_from_crud(): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'session_date' => now()->subDay()->toDateString(),
            ]));

        $this->actingAs($this->relation)
            ->post(route('admin.reports.remind'))
            ->assertRedirect();

        $this->assertSame(1, $this->coach->notifications()->count());
        $this->assertSame(1, $this->coach->notifications->first()->data['missing_count']);
    }

    public function test_pic_can_remind_additional_coach_of_own_school(): void
    {
        // Coach Dua hanya coach tambahan pada sesi di sekolah plot PIC A.
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->validPayload([
                'session_date' => now()->subDay()->toDateString(),
            ]));

        $this->actingAs($this->picA)
            ->post(route('pic.remind', ['coach_id' => $this->coachExtra->id]))
            ->assertRedirect();

        $this->assertSame(1, $this->coachExtra->notifications()->count());
    }
}
