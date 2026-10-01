{{--
    Bilah filter — pembungkus form GET pencarian/penyaringan.

    Dipakai untuk MENGGANTIKAN pola kartu filter yang sebelumnya berupa
    `<div class="card shadow-sm border-0 bg-light">` di setiap halaman daftar.
    Filter adalah kontrol kerja, bukan kartu promosi: komponen ini memakai
    satu garis tipis tanpa bayangan dan tanpa latar abu (lihat `.filter-bar`).

    PENTING — mengapa form-nya harus GET:
    Filter yang memakai POST akan (a) menambah entri history yang bisa dikirim
    ulang lewat tombol Back, dan (b) kehilangan kata kunci saat user menekan
    Back. Form GET menghasilkan URL yang bisa di-bookmark, di-Share, dan
    dipulihkan apa adanya — termasuk saat user kembali dari halaman detail.

    Pemakaian:
        <x-filter-bar :action="route('admin.coaches.index')" title="Cari coach">
            <div class="col-md-4">
                <label class="form-label" for="coachSearch">Nama atau email</label>
                <input id="coachSearch" type="text" name="search" class="form-control"
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary">Terapkan</button>
                @if (request()->hasAny(['search']))
                    <a href="{{ route('admin.coaches.index') }}" class="btn btn-outline-secondary">Reset</a>
                @endif
            </div>
        </x-filter-bar>

    Slot default = kolom-kolom grid (komponen menyediakan `row g-2` pembungkus).

    CATATAN: form GET tidak boleh memuat @csrf. Token CSRF pada URL akan
    membocorkan token ke riwayat browser, log server, dan header Referer.

    @param string      $action URL tujuan (route() halaman daftar itu sendiri).
    @param string|null $title  Label kecil di atas baris filter.
    @param string|null $reset  URL tombol "Reset"; bila diisi, tombol reset
                               dirender otomatis ketika ada filter aktif.
--}}
@props([
    'action',
    'title' => null,
    'reset' => null,
])

@php
    // Filter dianggap aktif bila URL membawa parameter selain nomor halaman.
    $hasActiveFilter = collect(request()->query())
        ->keys()
        ->reject(fn (string $key) => $key === 'page')
        ->isNotEmpty();
@endphp

<div class="filter-bar">
    @if ($title)
        <p class="filter-bar-title">{{ $title }}</p>
    @endif

    <form method="GET" action="{{ $action }}" role="search">
        <div class="row g-2 align-items-end">
            {{ $slot }}

            @if ($reset && $hasActiveFilter)
                <div class="col-auto">
                    <a href="{{ $reset }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            @endif
        </div>
    </form>
</div>
