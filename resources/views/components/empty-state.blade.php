{{--
    Keadaan kosong — satu pola untuk "belum ada data", dipakai di semua daftar.

    Sebelumnya setiap tabel menulis sendiri-sendiri: ada yang memakai
    `text-center text-muted py-5`, ada yang `<td colspan>` dengan ikon berbeda,
    ada yang hanya menampilkan tabel kosong tanpa penjelasan sama sekali.
    Perbedaan itu membuat user tidak tahu apakah datanya memang kosong atau
    halamannya gagal dimuat.

    Komponen ini WAJIB dipakai di dalam <td colspan="N"> saat menggantikan
    baris tabel, dan boleh juga dipakai di luar tabel.

    Pemakaian:
        @forelse ($coaches as $coach)
            <tr>...</tr>
        @empty
            <tr>
                <td colspan="{{ $columnCount }}">
                    <x-empty-state
                        icon="person-video3"
                        title="Belum ada coach"
                        description="Tambahkan coach pertama untuk mulai menugaskan kelas."
                    >
                        <button class="btn btn-primary btn-sm">Tambah Coach</button>
                    </x-empty-state>
                </td>
            </tr>
        @endforelse

    Slot default = aksi yang bisa langsung diambil user (opsional).

    @param string      $icon        Nama ikon Bootstrap Icons TANPA awalan `bi-`.
    @param string      $title       Satu baris: apa yang kosong.
    @param string|null $description Satu kalimat: kenapa kosong / apa langkah berikutnya.
--}}
@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
])

<div class="empty-state">
    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    <p class="empty-state-title">{{ $title }}</p>

    @if ($description)
        <p class="empty-state-text">{{ $description }}</p>
    @endif

    @unless ($slot->isEmpty())
        <div class="empty-state-action">{{ $slot }}</div>
    @endunless
</div>
