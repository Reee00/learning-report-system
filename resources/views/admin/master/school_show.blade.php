@extends('layouts.app')
@section('title', 'Detail Sekolah: ' . $school->name)

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('admin.schools.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Kembali ke Master Sekolah
            </a>
            <h4 class="mb-1 fw-bold"><i class="bi bi-building text-primary me-2"></i> {{ $school->name }}</h4>
            <p class="text-muted small mb-0">{{ $school->address ?? 'Belum ada alamat' }} | PIC: {{ $school->pic_name ?? '-' }}</p>
        </div>
    </div>

    <div class="row g-4">
        {{-- Daftar Kelas --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-journal-bookmark text-primary me-2"></i>Daftar Kelas ({{ $school->classes->count() }})</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($school->classes as $class)
                            <div class="list-group-item px-4 py-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="fw-bold">{{ $class->name }}</div>
                                    <span class="badge bg-light text-dark border"><i class="bi bi-people me-1"></i>{{ $class->students->count() }} Siswa</span>
                                </div>
                                @if($class->programs->isNotEmpty())
                                    <div class="mt-2">
                                        @foreach($class->programs as $prog)
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1 mb-1">{{ $prog->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="small text-muted fst-italic mt-1">Belum ada program</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                Belum ada kelas.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Daftar Program Terhubung --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-book-half text-primary me-2"></i>Program Terhubung ({{ $programs->count() }})</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($programs as $prog)
                            <div class="list-group-item px-4 py-3">
                                <div class="fw-bold mb-1">{{ $prog->name }}</div>
                                @if($prog->code)
                                    <span class="badge bg-light text-secondary border font-monospace">{{ $prog->code }}</span>
                                @endif
                                <div class="small text-muted mt-2">
                                    Digunakan di: 
                                    @php
                                        $progClasses = $school->classes->filter(fn($c) => $c->programs->contains('id', $prog->id));
                                    @endphp
                                    {{ $progClasses->pluck('name')->implode(', ') }}
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                Belum ada program terhubung.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
