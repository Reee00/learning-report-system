<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Nomor WhatsApp yang bisa diklik (review meeting LRS 2026-10-01).
 *
 * Aturan final yang dikunci di sini:
 * - nomor tampil sebagai tautan `https://wa.me/<62…>` bagi role yang berwenang
 *   (SuperAdmin, Relation, SPV Coach, PIC DK SCHOOL sesuai scope sekolahnya),
 *   dan sebagai nomor milik sendiri di Pengaturan Akun;
 * - bentuk internasional benar: `081234567890` → `6281234567890`;
 * - nomor kosong atau tidak dikenali TIDAK pernah menghasilkan tautan;
 * - PIC hanya melihat coach dalam scope sekolahnya, dan role tanpa wewenang
 *   tidak pernah menerima nomor (maupun tautan) siapa pun;
 * - tidak ada API/notifikasi WhatsApp — hanya tautan keluar biasa.
 */
class WhatsappLinkTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private User $coachA;
    private User $coachB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Alfa']);
        $this->schoolB = School::create(['name' => 'SD Beta']);

        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas 4A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas 4B']);

        $this->coachA = $this->makeUser(User::ROLE_COACH, 'coach.alfa', '081234567890');
        $this->coachB = $this->makeUser(User::ROLE_COACH, 'coach.beta', '081298765432');

        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classB->id]);
    }

    private function makeUser(string $role, string $slug, ?string $whatsapp = null): User
    {
        return User::create([
            'name'     => ucwords(str_replace('.', '_', $slug)),
            'email'    => $slug.'@test.test',
            'password' => Hash::make('password'),
            'role'     => $role,
            'whatsapp' => $whatsapp,
        ]);
    }

    private function picOfSchoolA(): User
    {
        $pic = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.alfa', '081200000009');
        $pic->schools()->attach($this->schoolA->id);

        return $pic;
    }

    // =====================================================================
    // Bentuk URL
    // =====================================================================

    public function test_a_canonical_number_becomes_the_right_wa_me_url(): void
    {
        $this->assertSame(
            'https://wa.me/6281234567890',
            $this->coachA->whatsappUrl()
        );
    }

    public function test_every_common_way_of_writing_the_number_produces_the_same_url(): void
    {
        foreach ([
            '081234567890',
            '+62 812-3456-7890',
            '62812 3456 7890',
            '0812-3456-7890',
            '812.3456.7890',
            ' 081234567890 ',
            '+6281234567890',
            '(0812) 3456 7890',
        ] as $input) {
            $user = new User(['whatsapp' => $input]);

            $this->assertSame(
                'https://wa.me/6281234567890',
                $user->whatsappUrl(),
                "Nomor '{$input}' menghasilkan tautan yang salah."
            );
        }
    }

    public function test_the_url_never_contains_a_separator_or_a_plus_sign(): void
    {
        $user = new User(['whatsapp' => '081234567890']);
        $url = (string) $user->whatsappUrl();

        $this->assertStringStartsWith('https://wa.me/62', $url);
        $this->assertMatchesRegularExpression('#^https://wa\.me/[0-9]+$#', $url);
    }

    public function test_an_empty_or_unusable_number_never_produces_a_link(): void
    {
        foreach ([null, '', '   ', 'abc-def', '12345', '0215551234', '62'] as $value) {
            $user = new User(['whatsapp' => $value]);

            $this->assertNull(
                $user->whatsappUrl(),
                "Nilai '".var_export($value, true)."' tidak boleh menghasilkan tautan."
            );
        }
    }

    // =====================================================================
    // Halaman daftar coach — role berwenang
    // =====================================================================

    public function test_authorised_roles_see_a_clickable_link_in_the_coach_list(): void
    {
        foreach ([User::ROLE_SUPERADMIN, User::ROLE_RELATION, User::ROLE_SPV_COACH] as $role) {
            $viewer = $this->makeUser($role, 'lihat.'.$role);

            $response = $this->actingAs($viewer)
                ->get(route('admin.coaches.index'))
                ->assertOk();

            $response->assertSee('https://wa.me/6281234567890', false);
            $response->assertSee('https://wa.me/6281298765432', false);
            // Tautan keluar: tab baru, tanpa akses ke window.opener.
            $response->assertSee('rel="noopener noreferrer"', false);
            $response->assertSee('target="_blank"', false);
            // Nomor tetap terbaca manusia.
            $response->assertSee('0812-3456-7890');
        }
    }

    public function test_the_coach_detail_page_renders_the_link_too(): void
    {
        $relation = $this->makeUser(User::ROLE_RELATION, 'relation.detail');

        $this->actingAs($relation)
            ->get(route('admin.coaches.show', $this->coachA))
            ->assertOk()
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee('0812-3456-7890');
    }

    public function test_a_coach_without_a_number_renders_no_link_at_all(): void
    {
        $this->coachA->update(['whatsapp' => null]);
        $relation = $this->makeUser(User::ROLE_RELATION, 'relation.kosong');

        $response = $this->actingAs($relation)
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->assertSee('Belum diatur');

        // Tidak ada tautan kosong/rusak yang ikut dirender.
        $response->assertDontSee('https://wa.me/"', false);
        $response->assertDontSee('wa.me/62"', false);
    }

    // =====================================================================
    // Scope PIC sekolah
    // =====================================================================

    public function test_pic_school_sees_a_link_only_for_coaches_inside_its_scope(): void
    {
        $pic = $this->picOfSchoolA();

        $response = $this->actingAs($pic)
            ->get(route('admin.coaches.index'))
            ->assertOk();

        $response->assertSee('https://wa.me/6281234567890', false);
        // Coach sekolah lain: nomor maupun tautannya tidak pernah dirender.
        $response->assertDontSee('https://wa.me/6281298765432', false);
        $response->assertDontSee('0812-9876-5432');
        $response->assertDontSee($this->coachB->name);
    }

    public function test_pic_school_gets_403_on_a_coach_outside_its_scope(): void
    {
        $pic = $this->picOfSchoolA();

        $this->actingAs($pic)
            ->get(route('admin.coaches.show', $this->coachA))
            ->assertOk()
            ->assertSee('https://wa.me/6281234567890', false);

        $this->actingAs($pic)
            ->get(route('admin.coaches.show', $this->coachB))
            ->assertForbidden();
    }

    public function test_a_coach_reaching_a_school_by_session_only_is_still_linked(): void
    {
        // Coach C hanya terhubung lewat sesi mengajar di sekolah A.
        $coachC = $this->makeUser(User::ROLE_COACH, 'coach.sesi', '081211112222');
        TeachingSchedule::create([
            'school_id'    => $this->schoolA->id,
            'class_id'     => $this->classA->id,
            'coach_id'     => $coachC->id,
            'session_date' => '2026-09-07',
        ]);

        $this->actingAs($this->picOfSchoolA())
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->assertSee('https://wa.me/6281211112222', false);
    }

    // =====================================================================
    // Role tanpa wewenang
    // =====================================================================

    public function test_roles_without_authority_never_receive_a_number_or_link(): void
    {
        foreach ([User::ROLE_TEACHER_SCHOOL, User::ROLE_FINANCE, User::ROLE_COACH] as $role) {
            $viewer = $this->makeUser($role, 'tanpa.'.$role);

            $this->actingAs($viewer)
                ->get(route('admin.coaches.index'))
                ->assertForbidden();

            $this->actingAs($viewer)
                ->get(route('admin.coaches.show', $this->coachA))
                ->assertForbidden();
        }
    }

    public function test_a_coach_never_sees_another_coachs_number(): void
    {
        $this->actingAs($this->coachA)
            ->get(route('admin.coaches.show', $this->coachB))
            ->assertForbidden();

        // Halaman akun sendiri hanya memuat nomor sendiri.
        $response = $this->actingAs($this->coachA)
            ->get(route('account.edit'))
            ->assertOk();

        $response->assertSee('https://wa.me/6281234567890', false);
        $response->assertDontSee('https://wa.me/6281298765432', false);
        $response->assertDontSee('0812-9876-5432');
    }

    // =====================================================================
    // Pengaturan Akun sendiri
    // =====================================================================

    public function test_a_user_can_click_their_own_number_from_account_settings(): void
    {
        $user = $this->makeUser(User::ROLE_TEACHER_SCHOOL, 'teacher.saya', '081255556666');

        $this->actingAs($user)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('https://wa.me/6281255556666', false)
            ->assertSee('0812-5555-6666');
    }

    public function test_account_settings_without_a_number_shows_belum_diatur_and_no_link(): void
    {
        $user = $this->makeUser(User::ROLE_FINANCE, 'finance.saya');

        $response = $this->actingAs($user)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Belum diatur');

        $response->assertDontSee('https://wa.me/', false);
    }

    // =====================================================================
    // Non-goal: tidak ada integrasi WhatsApp
    // =====================================================================

    public function test_the_application_never_calls_a_whatsapp_api(): void
    {
        // Satu-satunya kemunculan WhatsApp di kode aplikasi adalah pembentuk
        // tautan `wa.me`. Tidak ada endpoint API, token, atau job pengiriman.
        $offenders = [];

        foreach (['app', 'resources/views', 'routes', 'config'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                if (preg_match('#api\.whatsapp\.com|graph\.facebook\.com|wa\.me/send|WHATSAPP_TOKEN#', $contents)) {
                    $offenders[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'Tidak boleh ada integrasi/API WhatsApp.');
    }
}
