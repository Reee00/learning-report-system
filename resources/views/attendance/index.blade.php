@extends('layouts.app')
@section('title', 'Attendance')

@section('content')
<div class="container py-4">
    {{-- Halaman landing attendance: TIDAK ada tombol unduh (keputusan UX
         2026-09-14). Unduh hanya muncul di detail kelas terpilih, dengan
         konteks sekolah + kelas saat itu. --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item active" aria-current="page">Attendance</li>
                </ol>
            </nav>
            <h4 class="mb-1 fw-bold"><i class="bi bi-calendar-check text-primary me-2"></i> Data Kehadiran Siswa</h4>
            <p class="text-muted small mb-0">Pilih sekolah untuk melihat kehadiran per kelas dan tanggal.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <h6 class="mb-0 fw-bold">Terjadi Kesalahan</h6>
            </div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter: pencarian nama sekolah + rentang tanggal --}}
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-funnel-fill text-secondary me-2"></i> Filter Pencarian</h6>
            <form method="GET" action="{{ route('attendance.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold mb-1">Nama Sekolah</label>
                    <input type="text" name="search" class="form-control border-0 shadow-sm" placeholder="Cari sekolah..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control border-0 shadow-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control border-0 shadow-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 d-flex justify-content-end gap-2">
                    <a href="{{ route('attendance.index') }}" class="btn btn-light border shadow-sm px-3">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <button class="btn btn-primary shadow-sm px-4 fw-medium">
                        <i class="bi bi-search me-1"></i> Cari
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Daftar sekolah dengan data kehadiran (UX 2026-09-13: drill-down
         sekolah → kelas → tanggal; akumulasi murid hanya di dokumen unduh). --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
            <span class="fw-bold fs-6 text-dark">
                Sekolah dengan Data Kehadiran
                <span class="badge bg-primary rounded-pill ms-2">{{ $schools->total() }} Sekolah</span>
            </span>
        </div>
        <div class="card-body p-4">
            @forelse($schools as $schoolRow)
                <a href="{{ route('attendance.school', $schoolRow->school_id) }}"
                   class="d-block text-decoration-none border border-light-subtle rounded-3 p-3 mb-3 shadow-sm bg-white hover-bg-light">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary-subtle text-primary rounded-3 p-3 d-none d-md-block">
                                <i class="bi bi-building fs-4"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-6">{{ $schoolRow->school_name }}</div>
                                <small class="text-muted">
                                    {{ $schoolRow->classes_count }} kelas &bull; {{ $schoolRow->sessions_count }} sesi kehadiran
                                    @if($schoolRow->last_date)
                                        &bull; terakhir {{ \Carbon\Carbon::parse($schoolRow->last_date)->translatedFormat('d M Y') }}
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
                    <i class="bi bi-calendar-x fs-1 text-muted opacity-50 mb-3 d-block"></i>
                    <h6 class="text-muted mb-0">Tidak ada sekolah dengan data kehadiran yang sesuai filter.</h6>
                </div>
            @endforelse
        </div>
        @if($schools->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $schools->appends(request()->except('school_page'))->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
