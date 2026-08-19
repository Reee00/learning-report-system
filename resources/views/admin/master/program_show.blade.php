@extends('layouts.app')
@section('title', 'Detail Program: ' . $program->name)

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('admin.programs.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Kembali ke Master Program
            </a>
            <h4 class="mb-1 fw-bold"><i class="bi bi-book-half text-primary me-2"></i> {{ $program->name }}</h4>
            @if($program->code)
                <span class="badge bg-light text-secondary border font-monospace">{{ $program->code }}</span>
            @endif
            @if($program->status === 'active')
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 ms-2">Aktif</span>
            @else
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 ms-2">Tidak Aktif</span>
            @endif
        </div>
    </div>

    @if($program->description)
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-bold text-muted mb-2">Deskripsi Program</h6>
                <p class="mb-0">{{ $program->description }}</p>
            </div>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom-0 pt-4 pb-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-building text-primary me-2"></i>Sekolah yang Menggunakan Program Ini ({{ $program->programClasses->count() }} Kelas)</h6>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                @php
                    $groupedClasses = $program->programClasses->groupBy(fn($pc) => $pc->schoolClass->school->name);
                @endphp
                
                @forelse($groupedClasses as $schoolName => $pcs)
                    <div class="list-group-item px-4 py-3">
                        <div class="fw-bold mb-2">{{ $schoolName }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($pcs as $pc)
                                <span class="badge bg-light text-dark border border-secondary-subtle px-2 py-1">
                                    <i class="bi bi-journal-bookmark text-muted me-1"></i> {{ $pc->schoolClass->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                        Program ini belum terhubung dengan sekolah manapun.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
