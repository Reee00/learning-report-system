{{--
    Remah navigasi — satu komponen untuk seluruh aplikasi.

    Struktur hierarki di aplikasi ini selalu berbentuk
    "Modul -> Sekolah -> Kelas -> Sesi", dan sebelum komponen ini hanya empat
    halaman attendance yang punya remah. Halaman lain tidak menjawab
    "saya sedang di mana" selain dari judul.

    Pemakaian:
        <x-breadcrumb :items="[
            ['label' => 'Kehadiran', 'url' => route('attendance.index')],
            ['label' => $school->name, 'url' => route('attendance.school', $school)],
            ['label' => $class->name],
        ]" />

    Aturan:
    - item terakhir SELALU menjadi halaman aktif dan tidak dibuat tautan,
      walaupun `url`-nya diisi — supaya tidak ada tautan ke halaman sendiri;
    - `url` boleh dikosongkan untuk item yang bukan tautan;
    - item boleh berupa string biasa bila tidak ada tautannya.

    @param array $items Daftar ['label' => string, 'url' => ?string] atau string.
--}}
@props(['items' => []])

@php
    // Normalisasi: string dianggap label tanpa tautan, array dibaca label/url.
    $crumbs = collect($items)->map(function ($item) {
        if (is_array($item)) {
            return ['label' => $item['label'] ?? '', 'url' => $item['url'] ?? null];
        }

        return ['label' => (string) $item, 'url' => null];
    })->filter(fn (array $crumb) => $crumb['label'] !== '')->values();
@endphp

@if ($crumbs->isNotEmpty())
    <nav aria-label="Breadcrumb">
        <ol class="app-breadcrumb">
            @foreach ($crumbs as $crumb)
                @php $isCurrent = $loop->last; @endphp

                <li class="app-breadcrumb-item">
                    @if ($crumb['url'] && ! $isCurrent)
                        <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                    @else
                        <span @if ($isCurrent) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
