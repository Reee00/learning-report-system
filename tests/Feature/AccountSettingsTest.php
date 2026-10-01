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
 * Account Settings + Nomor WhatsApp (review meeting LRS 2026-10-01).
 *
 * Aturan final yang dikunci di sini:
 * - SETIAP role boleh membuka dan menyimpan pengaturan akunnya SENDIRI; email
 *   (identifier login) tidak ikut berubah;
 * - nomor disimpan ternormalisasi `08xxxxxxxxxx`, dan input tidak valid
 *   ditolak tanpa mengubah data lama;
 * - SuperAdmin / Relation / SPV Coach / PIC sekolah boleh melihat nomor
 *   WhatsApp coach; coach lain dan role tanpa wewenang tidak pernah menerima
 *   nomor siapa pun;
 * - PIC sekolah hanya melihat coach dalam scope sekolahnya — daftar maupun
 *   URL detail — dan scope itu ditegakkan di server, bukan di tampilan.
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private User $coachA;
    private User $coachB;
    private User $coachC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Alfa']);
        $this->schoolB = School::create(['name' => 'SD Beta']);

        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas 4A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas 4B']);

        $this->coachA = $this->makeUser(User::ROLE_COACH, 'coach.alfa', '081200000001');
        $this->coachB = $this->makeUser(User::ROLE_COACH, 'coach.beta', '081200000002');
        // coachC hanya terhubung lewat SESI mengajar — tanpa penugasan permanen.
        $this->coachC = $this->makeUser(User::ROLE_COACH, 'coach.sesi', '081200000003');

        CoachClass::create(['coach_id' => $this->coachA->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classB->id]);

        TeachingSchedule::create([
            'school_id'    => $this->schoolA->id,
            'class_id'     => $this->classA->id,
            'coach_id'     => $this->coachC->id,
            'session_date' => '2026-09-07',
        ]);
    }

    private function makeUser(string $role, string $slug, ?string $whatsapp = null): User
    {
        return User::create([
            'name'     => ucwords(str_replace('.', ' ', $slug)),
            'email'    => $slug.'@test.test',
            'password' => Hash::make('password'),
            'role'     => $role,
            'whatsapp' => $whatsapp,
        ]);
    }

    /** PIC sekolah A — hanya sekolah A yang diplot. */
    private function picOfSchoolA(): User
    {
        $pic = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.alfa', '081200000009');
        $pic->schools()->attach($this->schoolA->id);

        return $pic;
    }

    // =====================================================================
    // 1. Semua role bisa membuka Account Settings
    // =====================================================================

    public function test_every_role_can_open_its_own_account_settings(): void
    {
        foreach (User::roleKeys() as $role) {
            $user = $this->makeUser($role, 'akun.'.$role);

            $this->actingAs($user)
                ->get(route('account.edit'))
                ->assertOk()
                ->assertSee($user->name)
                ->assertSee($user->email)
                ->assertSee('Nomor WhatsApp');
        }
    }

    public function test_account_settings_never_shows_another_users_number(): void
    {
        $coach = $this->makeUser(User::ROLE_COACH, 'coach.sendiri', '081299998888');

        $response = $this->actingAs($coach)->get(route('account.edit'))->assertOk();

        // Nomornya sendiri tampil; nomor coach lain tidak pernah dirender.
        $response->assertSee('081299998888');
        $response->assertDontSee('081200000002'); // coach B
    }

    // =====================================================================
    // 2–3. Simpan & ubah nomor
    // =====================================================================

    public function test_a_user_can_save_a_whatsapp_number_in_any_common_format(): void
    {
        $user = $this->makeUser(User::ROLE_RELATION, 'relation.simpan');

        $this->actingAs($user)
            ->patch(route('account.update'), [
                'name'     => 'Relation Simpan',
                'whatsapp' => '+62 812-3456-7890',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Disimpan ternormalisasi, bukan apa adanya.
        $this->assertSame('081234567890', $user->refresh()->whatsapp);
    }

    public function test_a_user_can_change_and_then_clear_their_whatsapp_number(): void
    {
        $user = $this->makeUser(User::ROLE_COACH, 'coach.ubah', '081200000111');

        $this->actingAs($user)
            ->patch(route('account.update'), ['name' => 'Coach Ubah', 'whatsapp' => '081255556666'])
            ->assertSessionHas('success');

        $this->assertSame('081255556666', $user->refresh()->whatsapp);

        $this->actingAs($user)
            ->patch(route('account.update'), ['name' => 'Coach Ubah', 'whatsapp' => ''])
            ->assertSessionHas('success');

        $this->assertNull($user->refresh()->whatsapp);
        $this->assertFalse($user->hasWhatsapp());
    }

    public function test_email_is_not_changed_by_account_settings(): void
    {
        $user = $this->makeUser(User::ROLE_SPV_COACH, 'spv.email');

        $this->actingAs($user)
            ->patch(route('account.update'), [
                'name'     => 'SPV Email',
                'email'    => 'bajakan@test.test',
                'whatsapp' => '081200000222',
            ])
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('spv.email@test.test', $user->email);
        $this->assertSame('SPV Email', $user->name);
    }

    // =====================================================================
    // 4. Validasi nomor tidak valid
    // =====================================================================

    public function test_invalid_whatsapp_numbers_are_rejected_without_touching_saved_data(): void
    {
        $user = $this->makeUser(User::ROLE_COACH, 'coach.invalid', '081200000333');

        foreach (['12345', 'abc-def', '0215551234', '0812', '0812345678901234567'] as $invalid) {
            $this->actingAs($user)
                ->patch(route('account.update'), ['name' => 'Coach Invalid', 'whatsapp' => $invalid])
                ->assertSessionHasErrors('whatsapp');

            $this->assertSame(
                '081200000333',
                $user->refresh()->whatsapp,
                "Nomor lama tidak boleh berubah saat input '{$invalid}' ditolak."
            );
        }
    }

    public function test_an_empty_name_is_rejected(): void
    {
        $user = $this->makeUser(User::ROLE_COACH, 'coach.nama');

        $this->actingAs($user)
            ->patch(route('account.update'), ['name' => '', 'whatsapp' => '081200000444'])
            ->assertSessionHasErrors('name');
    }

    // =====================================================================
    // 5. Coach melihat nomornya sendiri
    // =====================================================================

    public function test_a_coach_sees_their_own_number_in_a_readable_format(): void
    {
        $coach = $this->makeUser(User::ROLE_COACH, 'coach.lihat', '081234567890');

        $this->actingAs($coach)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('0812-3456-7890');
    }

    public function test_a_coach_without_a_number_sees_belum_diatur(): void
    {
        $coach = $this->makeUser(User::ROLE_COACH, 'coach.kosong');

        $this->actingAs($coach)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Belum diatur');
    }

    // =====================================================================
    // 6–8. SuperAdmin / Relation / SPV melihat nomor coach
    // =====================================================================

    public function test_superadmin_relation_and_spv_see_coach_numbers_in_the_list(): void
    {
        foreach ([User::ROLE_SUPERADMIN, User::ROLE_RELATION, User::ROLE_SPV_COACH] as $role) {
            $viewer = $this->makeUser($role, 'lihat.'.$role);

            $this->actingAs($viewer)
                ->get(route('admin.coaches.index'))
                ->assertOk()
                ->assertSee('Nomor WhatsApp')
                ->assertSee('0812-0000-0001')  // coach A
                ->assertSee('0812-0000-0002')  // coach B (global scope)
                ->assertSee('0812-0000-0003'); // coach C (lewat sesi mengajar)
        }
    }

    public function test_the_coach_detail_page_shows_the_number_to_permitted_roles(): void
    {
        $relation = $this->makeUser(User::ROLE_RELATION, 'relation.detail');

        $this->actingAs($relation)
            ->get(route('admin.coaches.show', $this->coachA))
            ->assertOk()
            ->assertSee('Nomor WhatsApp')
            ->assertSee('0812-0000-0001');
    }

    public function test_a_coach_without_a_number_is_displayed_as_belum_diatur(): void
    {
        $relation = $this->makeUser(User::ROLE_RELATION, 'relation.kosong');
        $this->coachA->update(['whatsapp' => null]);

        $this->actingAs($relation)
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->assertSee('Belum diatur');
    }

    // =====================================================================
    // 9. PIC sekolah hanya melihat coach dalam scope sekolahnya
    // =====================================================================

    public function test_pic_school_only_sees_coaches_within_its_school_scope(): void
    {
        $pic = $this->picOfSchoolA();

        $this->actingAs($pic)
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->assertSee($this->coachA->name)   // penugasan kelas di sekolah A
            ->assertSee($this->coachC->name)   // sesi mengajar di sekolah A
            ->assertDontSee($this->coachB->name)
            ->assertDontSee('0812-0000-0002');
    }

    public function test_pic_school_gets_403_on_a_coach_from_another_school(): void
    {
        $pic = $this->picOfSchoolA();

        $this->actingAs($pic)
            ->get(route('admin.coaches.show', $this->coachA))
            ->assertOk();

        $this->actingAs($pic)
            ->get(route('admin.coaches.show', $this->coachB))
            ->assertForbidden();
    }

    public function test_pic_school_without_any_plotted_school_sees_no_coach_at_all(): void
    {
        $pic = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.kosong');

        $this->actingAs($pic)
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->assertDontSee($this->coachA->name)
            ->assertDontSee($this->coachB->name);

        $this->actingAs($pic)
            ->get(route('admin.coaches.show', $this->coachA))
            ->assertForbidden();
    }

    // =====================================================================
    // 10–11. Coach lain & role tanpa wewenang tidak dapat akses
    // =====================================================================

    public function test_a_coach_cannot_reach_the_coach_list_or_another_coachs_number(): void
    {
        $this->actingAs($this->coachA)
            ->get(route('admin.coaches.index'))
            ->assertForbidden();

        $this->actingAs($this->coachA)
            ->get(route('admin.coaches.show', $this->coachB))
            ->assertForbidden();
    }

    public function test_roles_without_coach_authority_get_403_on_direct_urls(): void
    {
        foreach ([User::ROLE_TEACHER_SCHOOL, User::ROLE_FINANCE] as $role) {
            $viewer = $this->makeUser($role, 'tanpa.'.$role);

            $this->actingAs($viewer)
                ->get(route('admin.coaches.index'))
                ->assertForbidden();

            $this->actingAs($viewer)
                ->get(route('admin.coaches.show', $this->coachA))
                ->assertForbidden();
        }
    }

    public function test_account_settings_cannot_be_pointed_at_another_user(): void
    {
        $user = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.target');

        // Id user lain ditaruh di URL dan body — keduanya harus diabaikan.
        $this->actingAs($user)
            ->patch(route('account.update').'?user_id='.$this->coachB->id, [
                'user_id'  => $this->coachB->id,
                'id'       => $this->coachB->id,
                'name'     => 'Bukan Nama Saya',
                'whatsapp' => '081277778888',
            ])
            ->assertSessionHas('success');

        $this->assertSame('081277778888', $user->refresh()->whatsapp);
        $this->assertSame('Bukan Nama Saya', $user->name);

        // Data coach lain tidak tersentuh.
        $this->coachB->refresh();
        $this->assertSame('081200000002', $this->coachB->whatsapp);
        $this->assertNotSame('Bukan Nama Saya', $this->coachB->name);
    }

    // =====================================================================
    // 12. Data user yang sudah ada tetap aman
    // =====================================================================

    public function test_existing_users_keep_their_data_and_default_to_no_number(): void
    {
        $existing = User::create([
            'name'     => 'User Lama',
            'email'    => 'user.lama@test.test',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_RELATION,
        ]);

        $existing->refresh();

        $this->assertNull($existing->whatsapp);
        $this->assertFalse($existing->hasWhatsapp());
        $this->assertNull($existing->whatsappDisplay());

        // Kolom baru tidak mengubah apa pun pada baris lama.
        $this->assertSame('User Lama', $existing->name);
        $this->assertSame('user.lama@test.test', $existing->email);
        $this->assertSame(User::ROLE_RELATION, $existing->role);
    }

    public function test_whatsapp_is_normalized_and_displayed_consistently(): void
    {
        $this->assertSame('081234567890', User::normalizeWhatsapp('+62 812-3456-7890'));
        $this->assertSame('081234567890', User::normalizeWhatsapp('62812 3456 7890'));
        $this->assertSame('081234567890', User::normalizeWhatsapp('081234567890'));
        $this->assertSame('081234567890', User::normalizeWhatsapp('812.3456.7890'));
        $this->assertNull(User::normalizeWhatsapp(''));
        $this->assertNull(User::normalizeWhatsapp('   '));
        $this->assertNull(User::normalizeWhatsapp(null));

        // Bukan angka sama sekali = salah ketik, bukan penghapusan nomor.
        $this->assertSame('abc-def', User::normalizeWhatsapp('abc-def'));

        $user = $this->makeUser(User::ROLE_COACH, 'coach.format', '081234567890');
        $this->assertSame('0812-3456-7890', $user->whatsappDisplay());
    }
}
