@php
    $accidentNotes = trim((string) ($notes ?? ''));
    $headingId = 'accident-notes-title-'.($reportId ?? 'current');
    // Opsional: tautan ke laporan asal catatan ini. Dipakai daftar pengingat
    // pribadi coach, tidak dipakai halaman detail laporan.
    $accidentReportUrl = $reportUrl ?? null;
@endphp

@if($accidentNotes !== '')
    <section class="card border-danger mb-3" role="alert" aria-labelledby="{{ $headingId }}">
        <div class="card-header bg-danger text-white d-flex align-items-center gap-2">
            <span aria-hidden="true">&#9888;</span>
            <strong id="{{ $headingId }}">Accident Notes</strong>
            <span class="badge bg-light text-danger ms-auto">Urgent</span>
        </div>
        <div class="card-body bg-danger-subtle">
            <p class="mb-0" style="white-space: pre-line">{{ $accidentNotes }}</p>
            @if($accidentReportUrl)
                <a href="{{ $accidentReportUrl }}" class="btn btn-sm btn-outline-danger rounded-pill mt-3">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Lihat Laporan
                </a>
            @endif
        </div>
    </section>
@endif
