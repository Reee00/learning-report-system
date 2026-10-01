{{--
    Komponen bersama: tampilan nomor WhatsApp yang bisa diklik
    (review meeting LRS 2026-10-01).

    Cara pakai:
        @include('partials.whatsapp-link', ['person' => $coach])

    Tiga keadaan keluaran:
    - nomor ada dan bentuknya dikenali  → tautan `https://wa.me/<62…>`
    - nomor ada tetapi bentuknya aneh   → teks biasa (tanpa tautan yang salah)
    - nomor kosong                      → "Belum diatur"

    KEAMANAN: komponen ini hanya merender, tidak memutuskan siapa yang boleh
    melihat. Nomor yang tidak boleh dibaca sudah DIBUANG di sisi server oleh
    pemanggilnya (mis. `Admin\CoachController::hideContactWhenNotPermitted()`
    menyetel `whatsapp` menjadi null). Jadi jangan memakai partial ini sebagai
    pengganti pemeriksaan izin — ia hanya menyajikan nilai yang sudah lolos.

    Tautan dibuka di tab baru dengan `rel="noopener noreferrer"` supaya halaman
    aplikasi tidak dapat diakses lewat `window.opener` oleh situs tujuan.
    Tidak ada API WhatsApp dan tidak ada notifikasi yang dikirim — ini tautan
    keluar biasa.
--}}
@php
    $waUrl = $person->whatsappUrl();
    $waText = $person->whatsappDisplay();
@endphp
@if($waUrl)
    <a href="{{ $waUrl }}"
       target="_blank"
       rel="noopener noreferrer"
       class="text-success text-decoration-none fw-medium"
       title="Buka chat WhatsApp ke {{ $waText }}">
        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>{{ $waText }}<span class="visually-hidden"> (buka di tab baru)</span>
    </a>
@elseif($waText)
    <span class="fw-medium text-dark">{{ $waText }}</span>
@else
    <span class="text-muted fst-italic">Belum diatur</span>
@endif
