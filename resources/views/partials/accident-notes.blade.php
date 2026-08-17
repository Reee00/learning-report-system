@php
    $accidentNotes = trim((string) ($notes ?? ''));
    $headingId = 'accident-notes-title-'.($reportId ?? 'current');
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
        </div>
    </section>
@endif
