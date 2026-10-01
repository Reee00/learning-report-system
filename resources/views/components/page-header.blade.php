{{--
    Sampul halaman — judul, deskripsi singkat, remah navigasi, dan baris aksi.

    Menggantikan pola yang sebelumnya disalin ke puluhan halaman:

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h4 class="fw-bold"><i class="bi bi-... text-primary"></i> Judul</h4>
                 <p class="text-muted small mb-0">Deskripsi</p></div>
            ...tombol...
        </div>

    Pola lama itu yang membuat setiap halaman punya proporsi dan jarak yang
    sedikit berbeda. Komponen ini memakai satu definisi (`.page-header`).

    Pemakaian:
        <x-page-header
            title="Master Data Coach"
            description="Kelola daftar coach dan penugasan kelas mereka."
            :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Coach']]"
        >
            <button class="btn btn-primary">Tambah Coach</button>
        </x-page-header>

    Slot default = baris aksi di kanan (boleh kosong).
    Slot `meta`   = baris kecil di bawah deskripsi (mis. badge status, jumlah data).

    Judul dirender sebagai <h1>: satu halaman satu h1, dan topbar tetap
    memakai teksnya sendiri. Sebelumnya setiap halaman memakai <h4> sehingga
    hierarki heading tidak pernah benar untuk pembaca layar.

    @param string      $title
    @param string|null $description
    @param array       $breadcrumbs
--}}
@props([
    'title',
    'description' => null,
    'breadcrumbs' => [],
])

<header class="page-header">
    @if (! empty($breadcrumbs))
        <x-breadcrumb :items="$breadcrumbs" />
    @endif

    <div class="page-header-main">
        <div>
            <h1 class="page-title">{{ $title }}</h1>

            @if ($description)
                <p class="page-subtitle">{{ $description }}</p>
            @endif

            @isset($meta)
                <div class="mt-2">{{ $meta }}</div>
            @endisset
        </div>

        @unless ($slot->isEmpty())
            <div class="page-header-actions">{{ $slot }}</div>
        @endunless
    </div>
</header>
