<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kesalahan validasi harus terlihat (overhaul UX/UI 2026-10-01, §6).
 *
 * MASALAH YANG DIKUNCI
 *
 * Sebagian besar form tambah/edit di LRS berada DI DALAM modal Bootstrap:
 * daftar coach, daftar akun, master sekolah, master program, master program
 * kelas. Pola controller-nya benar — `back()->withInput()->withErrors()` —
 * tetapi hasilnya halaman daftar dirender ulang dengan modal TERTUTUP.
 *
 * Akibatnya user tidak melihat pesan error sama sekali: daftar tampak tidak
 * berubah, dan kesimpulannya adalah "tombol simpan tidak bekerja". Ini bukan
 * bug validasi, melainkan bug visibilitas.
 *
 * Perbaikannya ada di `partials/modal-reopen`: setelah redirect, modal yang
 * gagal dibuka kembali. Supaya halaman dengan beberapa modal (tambah + edit)
 * bisa membuka yang BENAR, setiap form mengirim penanda tersembunyi
 * `_modal` (dan `_modal_id` untuk baris yang sedang diedit).
 *
 * Yang diuji:
 *
 *  1. validasi gagal -> modal yang benar dibuka kembali dan isian lama utuh;
 *  2. modal yang TIDAK terlibat tidak ikut terbuka;
 *  3. halaman biasa (tanpa old input) tidak membuka modal apa pun;
 *  4. setiap form di dalam modal di aplikasi ini punya penanda `_modal`,
 *     supaya tidak ada halaman baru yang diam-diam mengulang bug lama.
 */
class FormErrorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private ?User $superadmin = null;

    private function superadmin(): User
    {
        return $this->superadmin ??= User::create([
            'name'     => 'Superadmin Form',
            'email'    => 'superadmin.form@test.test',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_SUPERADMIN,
        ]);
    }

    /**
     * Meniru validasi gagal: kirim payload yang tidak lolos, lalu ikuti
     * redirect-nya dan periksa HTML yang benar-benar diterima user.
     */
    public function test_a_failed_coach_creation_reopens_the_add_modal_with_the_old_input(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->from(route('admin.coaches.index'))
            ->post(route('admin.coaches.store'), [
                '_modal'   => 'add',
                'name'     => 'Coach Tanpa Email',
                'email'    => 'bukan-email',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ]);

        // PRG: tidak ada render hasil POST, hanya redirect kembali ke form.
        $response->assertRedirect(route('admin.coaches.index'));
        $response->assertSessionHasErrors('email');

        $html = $this->actingAs($this->superadmin())
            ->withSession($response->getSession()->all())
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->getContent();

        // Skrip pembuka modal harus benar-benar ikut ter-render, dan syaratnya
        // menilai true untuk modal tambah coach.
        $this->assertStringContainsString('var hasOldInput = true;', $html);
        $this->assertStringContainsString('addCoachModal', $html);
        $this->assertStringContainsString('var shouldOpen = true;', $html);

        // Isian lama ikut kembali supaya user tidak perlu mengetik ulang.
        $this->assertStringContainsString('Coach Tanpa Email', $html);
    }

    public function test_the_edit_modal_is_not_opened_when_the_add_form_failed(): void
    {
        $html = $this->actingAs($this->superadmin())
            ->withSession([
                '_old_input' => [
                    '_modal' => 'add',
                    'name'   => 'Coach Tanpa Email',
                    'email'  => 'bukan-email',
                ],
            ])
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->getContent();

        // Halaman coach punya dua modal (tambah + edit). Karena penandanya
        // 'add', hanya SATU yang boleh dibuka — kalau keduanya terbuka,
        // user melihat form edit yang bukan sumber error-nya.
        $this->assertSame(
            1,
            substr_count($html, 'var shouldOpen = true;'),
            'Tepat satu modal boleh dibuka kembali saat validasi gagal.'
        );
        $this->assertSame(
            1,
            substr_count($html, 'var shouldOpen = false;'),
            'Modal yang tidak terlibat harus dibiarkan tertutup.'
        );
    }

    public function test_a_normally_opened_list_page_opens_no_modal(): void
    {
        $html = $this->actingAs($this->superadmin())
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->getContent();

        // Tanpa old input, syaratnya false — jadi tidak ada modal yang
        // muncul sendiri saat halaman dibuka biasa.
        $this->assertStringContainsString('var hasOldInput = false;', $html);
    }

    /**
     * Penjaga untuk halaman yang BELUM ada: form di dalam modal apa pun harus
     * mengirim penanda `_modal`, karena tanpa itu modal-reopen tidak punya
     * cara membedakan modal tambah dari modal edit.
     */
    public function test_every_modal_form_in_the_application_carries_a_reopen_marker(): void
    {
        $views = [
            'admin/master/coaches.blade.php',
            'admin/master/classes.blade.php',
            'admin/master/programs.blade.php',
            'admin/master/schools.blade.php',
            'admin/users/index.blade.php',
        ];

        foreach ($views as $relative) {
            $source = file_get_contents(resource_path('views/'.$relative));

            preg_match_all('/<form\b[^>]*method="POST"[^>]*>(.*?)<\/form>/si', $source, $matches, PREG_SET_ORDER);

            $modalForms = 0;

            foreach ($matches as $match) {
                // Form di dalam modal dikenali dari modal-footer-nya — cara
                // paling tidak rapuh tanpa parser HTML.
                if (! str_contains($match[1], 'modal-footer')) {
                    continue;
                }

                // Modal hapus hanya meminta konfirmasi; tidak ada isian yang
                // bisa hilang, jadi tidak perlu dibuka ulang.
                if (str_contains($match[1], "@method('DELETE')") || str_contains($match[1], '@method("DELETE")')) {
                    continue;
                }

                $modalForms++;

                $this->assertStringContainsString(
                    'name="_modal"',
                    $match[1],
                    "Form di dalam modal pada {$relative} tidak mengirim penanda _modal, "
                    .'sehingga pesan validasinya tidak akan terlihat (modal tidak dibuka ulang).'
                );
            }

            $this->assertGreaterThan(
                0,
                $modalForms,
                "Tidak menemukan form modal di {$relative} — penjaga ini jadi tidak berguna."
            );
        }
    }
}
