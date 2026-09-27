<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Komponen UI bersama (2026-09-24):
 *
 * - Paginasi: satu view bersama di resources/views/vendor/pagination/
 *   dipakai seluruh halaman yang memanggil ->links(). Label berbahasa
 *   Indonesia tanpa @lang()/__(), sehingga tidak ada lagi string mentah
 *   "pagination.previous" / "Showing" / "of" / "results" (aplikasi ini
 *   tidak punya folder lang/).
 * - Form sekolah responsif: struktur dua kolom di desktop yang menumpuk
 *   satu kolom di tablet/ponsel, tanpa overflow horizontal.
 * - Angka tidak terpecah antar digit (overflow-wrap: break-word, bukan
 *   overflow-wrap: anywhere).
 */
class SharedUiComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_paginator_view_is_bootstrap_five(): void
    {
        $this->assertSame('pagination::bootstrap-5', Paginator::$defaultView);
    }

    public function test_shared_pagination_view_uses_indonesian_labels_without_lang_keys(): void
    {
        // Komentar dokumentasi menyebut key lama sebagai penjelasan, jadi
        // yang diperiksa adalah markup-nya saja.
        $view = $this->withoutBladeComments(
            file_get_contents(resource_path('views/vendor/pagination/bootstrap-5.blade.php'))
        );

        // Label Indonesia eksplisit.
        $this->assertStringContainsString('Sebelumnya', $view);
        $this->assertStringContainsString('Berikutnya', $view);
        $this->assertStringContainsString('Menampilkan', $view);

        // Tidak ada pemanggilan @lang()/__() yang bisa bocor sebagai key mentah.
        $this->assertStringNotContainsString('@lang(', $view);
        $this->assertStringNotContainsString("__('", $view);
        $this->assertStringNotContainsString('pagination.previous', $view);
        $this->assertStringNotContainsString('pagination.next', $view);

        // Ikon Bootstrap Icons, bukan entitas &lsaquo;/&rsaquo;.
        $this->assertStringContainsString('bi-chevron-left', $view);
        $this->assertStringContainsString('bi-chevron-right', $view);
        $this->assertStringNotContainsString('&lsaquo;', $view);
        $this->assertStringNotContainsString('&rsaquo;', $view);

        // Status halaman aktif dan disabled ditandai eksplisit.
        $this->assertStringContainsString('page-item active', $view);
        $this->assertStringContainsString('page-item disabled', $view);
    }

    public function test_every_page_using_links_renders_shared_pagination_markup(): void
    {
        $relation = $this->makeUser(User::ROLE_RELATION);

        // 20 sekolah > 15 per halaman -> paginasi muncul.
        foreach (range(1, 20) as $i) {
            School::create(['name' => 'Sekolah '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }
        Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);

        $response = $this->actingAs($relation)
            ->get(route('admin.schools.index'))
            ->assertOk();

        $response->assertSee('Menampilkan');
        $response->assertSee('Sebelumnya');
        $response->assertSee('Berikutnya');
        $response->assertSee('page-item active', false);

        // Tidak ada key bahasa yang bocor.
        $response->assertDontSee('pagination.previous');
        $response->assertDontSee('pagination.next');
        $response->assertDontSee('&lsaquo;', false);
        $response->assertDontSee('&rsaquo;', false);
    }

    public function test_pagination_and_row_numbering_continue_across_pages(): void
    {
        $relation = $this->makeUser(User::ROLE_RELATION);

        foreach (range(1, 20) as $i) {
            School::create(['name' => 'Sekolah '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        // Halaman 1: nomor baris dimulai dari 1.
        $this->actingAs($relation)
            ->get(route('admin.schools.index'))
            ->assertOk()
            ->assertSee('Sekolah 01');

        // Halaman 2: nomor baris melanjutkan (16..20), bukan mengulang dari 1.
        $this->actingAs($relation)
            ->get(route('admin.schools.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Sekolah 20')
            ->assertDontSee('Sekolah 01');
    }

    public function test_school_add_form_uses_responsive_two_column_structure(): void
    {
        School::create(['name' => 'Sekolah Ada']);
        Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);

        $response = $this->actingAs($this->makeUser(User::ROLE_RELATION))
            ->get(route('admin.schools.index'))
            ->assertOk();

        // Dua kolom seimbang di desktop, menumpuk di bawah breakpoint lg.
        $response->assertSee('col-12 col-lg-6', false);
        $response->assertSee('modal-lg', false);
        // Modal penuh di ponsel -> tidak ada overflow horizontal.
        $response->assertSee('modal-fullscreen-sm-down', false);
        // Footer modal: tombol selebar penuh di ponsel, rata kanan di layar besar.
        $response->assertSee('d-grid gap-2 d-sm-flex justify-content-sm-end', false);

        // Label setiap field terkait ke input-nya (aksesibilitas).
        $response->assertSee('for="addSchoolName"', false);
        $response->assertSee('id="addSchoolName"', false);
        $response->assertSee('for="addSchoolPrograms"', false);
        $response->assertSee('id="addSchoolPrograms"', false);

        // Bagian formulir dipisah dengan judul yang jelas.
        $response->assertSee('Informasi Sekolah');
        $response->assertSee('Setup Awal');
    }

    public function test_school_form_keeps_old_input_after_validation_error(): void
    {
        $relation = $this->makeUser(User::ROLE_RELATION);

        $this->actingAs($relation)
            ->from(route('admin.schools.index'))
            ->post(route('admin.schools.store'), [
                'name' => '',
                'pic_name' => 'PIC Lama',
            ])
            ->assertRedirect(route('admin.schools.index'))
            ->assertSessionHasErrors('name');

        // Nilai yang sudah diketik tetap terisi dan modal dibuka kembali.
        $this->actingAs($relation)
            ->get(route('admin.schools.index'))
            ->assertOk()
            ->assertSee('PIC Lama')
            ->assertSee('addSchoolModal');
    }

    public function test_number_cells_do_not_split_digits(): void
    {
        // Komentar CSS menyebut pola lama sebagai penjelasan; yang diperiksa
        // adalah deklarasi CSS-nya.
        $layout = $this->withoutCssComments(
            file_get_contents(resource_path('views/layouts/app.blade.php'))
        );

        // overflow-wrap: anywhere menciutkan min-content sel menjadi 1 karakter
        // sehingga "10" terpotong menjadi "1" + "0".
        $this->assertStringNotContainsString('overflow-wrap: anywhere;', $layout);
        $this->assertStringContainsString('overflow-wrap: break-word;', $layout);
        $this->assertStringContainsString('word-break: normal;', $layout);
        // Badge (mis. jumlah murid) tidak boleh terpecah antar baris.
        $this->assertStringContainsString('white-space: nowrap;', $layout);
    }

    public function test_class_index_shows_usage_counts_without_wrapping(): void
    {
        $school = School::create(['name' => 'Sekolah Hitung']);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Grade 10']);
        $class->students()->create(['name' => 'Murid Satu']);

        $this->actingAs($this->makeUser(User::ROLE_RELATION))
            ->get(route('admin.classes.index'))
            ->assertOk()
            // Angka dua digit tampil utuh, bukan terpecah antar digit.
            ->assertSee('1 murid')
            ->assertSee('0 jadwal')
            ->assertSee('0 laporan');
    }

    private function withoutBladeComments(string $contents): string
    {
        return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contents);
    }

    private function withoutCssComments(string $contents): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $contents);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' Test',
            'email' => $role.'-ui@test.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
