@extends('layouts.app')
@section('title', 'Attendance — ' . $school->name)

@section('content')
{{-- Detail sekolah: TIDAK ada tombol unduh (keputusan UX 2026-09-14) —
     unduh kelas harus menunggu kelas terpilih di halaman detail kelas. --}}
<div class="container py-4">
    <x-breadcrumb :items="[
        ['label' => 'Attendance', 'url' => ctx_route('attendance.index', [], true)],
        ['label' => $school->name],
    ]" />
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <a href="{{ ctx_route('attendance.index', [], true) }}" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <h1 class="h4 mb-1 fw-semibold">{{ $school->name }}</h1>
            <p class="text-muted small mb-0">Pilih kelas untuk melihat sesi kehadiran per tanggal.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filter rentang tanggal sesi --}}
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('attendance.school', $school) }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control border-0 shadow-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control border-0 shadow-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('attendance.school', $school) }}" class="btn btn-light border shadow-sm px-3">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <button class="btn btn-primary shadow-sm px-4 fw-medium">
                        <i class="bi bi-search me-1"></i> Terapkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Daftar kelas dengan data kehadiran di sekolah ini --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
            <span class="fw-bold fs-6 text-dark">
                Kelas dengan Data Kehadiran
                <span class="badge bg-primary rounded-pill ms-2">{{ $classes->total() }} Kelas</span>
            </span>
        </div>
        <div class="card-body p-4">
            @forelse($classes as $classRow)
                @php
                    $classModel = $programsByClass->get($classRow->class_id);
                    $programs = $classModel ? $classModel->programs->pluck('name')->implode(', ') : '';
                @endphp
                <a href="{{ ctx_route('attendance.class', [$school, $classRow->class_id]) }}"
                   class="d-block text-decoration-none border border-light-subtle rounded-3 p-3 mb-3 shadow-sm bg-white">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary-subtle text-primary rounded-3 p-3 d-none d-md-block">
                                <i class="bi bi-people fs-4"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-6">
                                    {{ $classRow->class_name }}
                                    @if($programs !== '')
                                        <span class="badge bg-light text-dark border border-secondary-subtle ms-1">{{ $programs }}</span>
                                    @endif
                                </div>
                                <small class="text-muted">
                                    {{ $classRow->students_count }} murid tercatat &bull; {{ $classRow->sessions_count }} sesi kehadiran
                                    @if($classRow->last_date)
                                        &bull; terakhir {{ \Carbon\Carbon::parse($classRow->last_date)->translatedFormat('d M Y') }}
                                    @endif
                                </small>
                            </div>
                        </div>
                        <div class="text-primary">
                            <i class="bi bi-chevron-right"></i>
                        </div>
                    </div>
                </a>
            @empty
                <div class="text-center py-5">
                    <i class="bi bi-people fs-1 text-muted opacity-50 mb-3 d-block"></i>
                    <h6 class="text-muted mb-0">Belum ada kelas dengan data kehadiran yang sesuai filter.</h6>
                </div>
            @endforelse
        </div>
        @if($classes->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $classes->appends(request()->except('class_page'))->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
