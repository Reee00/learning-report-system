@extends('layouts.app')
@section('title', 'Attendance — ' . $class->name)

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canExportCsv = $authorization->allows($currentUser, 'attendance.export')
        || $authorization->allows($currentUser, 'attendance.export_csv');
    $canExportPdf = $authorization->allows($currentUser, 'attendance.export');
@endphp

<div class="container py-4">
    <x-page-header
        title="{{ $class->name }}"
        description="{{ $school->name }} — pilih tanggal sesi untuk melihat kehadiran per murid."
        :breadcrumbs="[
            ['label' => 'Attendance', 'url' => ctx_route('attendance.index', [], true)],
            ['label' => $school->name, 'url' => ctx_route('attendance.school', $school, true)],
            ['label' => $class->name],
        ]"
    >
        <x-slot:meta>
            <a href="{{ ctx_route('attendance.school', $school, true) }}" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali ke {{ $school->name }}
            </a>
        </x-slot:meta>

        {{-- Satu-satunya lokasi tombol unduh (keputusan UX 2026-09-14):
             detail kelas terpilih. Konteks school_id + class_id otomatis
             dari halaman ini; otorisasi & scope tetap divalidasi server. --}}
        @if($canExportCsv || $canExportPdf)
            @if($canExportPdf)
                <a href="{{ route('attendance.export', array_merge(request()->only(['date_from', 'date_to']), ['school_id' => $school->id, 'class_id' => $class->id, 'format' => 'excel'])) }}" class="btn btn-success d-inline-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Unduh Excel
                </a>
                <a href="{{ route('attendance.export', array_merge(request()->only(['date_from', 'date_to']), ['school_id' => $school->id, 'class_id' => $class->id, 'format' => 'pdf'])) }}" class="btn btn-danger d-inline-flex align-items-center gap-2">
                    <i class="bi bi-filetype-pdf" aria-hidden="true"></i> Unduh PDF
                </a>
            @endif
            @if($canExportCsv)
                <a href="{{ route('attendance.export', array_merge(request()->only(['date_from', 'date_to']), ['school_id' => $school->id, 'class_id' => $class->id, 'format' => 'csv'])) }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" title="Data mentah untuk integrasi sistem">
                    <i class="bi bi-filetype-csv" aria-hidden="true"></i> Unduh CSV
                </a>
            @endif
        @endif
    </x-page-header>

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
            <form method="GET" action="{{ route('attendance.class', [$school, $class]) }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control border-0 shadow-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control border-0 shadow-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('attendance.class', [$school, $class]) }}" class="btn btn-light border shadow-sm px-3">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    <button class="btn btn-primary shadow-sm px-4 fw-medium">
                        <i class="bi bi-search me-1"></i> Terapkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Sesi kehadiran dikelompokkan per tanggal (satu laporan per sesi) --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
            <span class="fw-bold fs-6 text-dark">
                Sesi Kehadiran per Tanggal
                <span class="badge bg-primary rounded-pill ms-2">{{ $sessions->total() }} Sesi</span>
            </span>
        </div>
        <div class="card-body p-4">
            @php
                // Kelompokkan sesi halaman ini per tanggal (bisa ada >1 sesi
                // pada tanggal yang sama).
                $sessionsByDate = $sessions->getCollection()->groupBy(
                    fn ($report) => $report->report_date->format('Y-m-d')
                );
            @endphp
            @forelse($sessionsByDate as $dateKey => $dateSessions)
                <div class="mb-4">
                    <div class="fw-semibold small text-dark mb-2 text-uppercase" style="letter-spacing: 0.5px;">
                        <i class="bi bi-calendar3 text-primary me-1"></i>
                        {{ \Carbon\Carbon::parse($dateKey)->translatedFormat('l, d M Y') }}
                    </div>
                    @foreach($dateSessions as $session)
                        <a href="{{ ctx_route('attendance.session', $session) }}"
                           class="d-block text-decoration-none border border-light-subtle rounded-3 p-3 mb-2 shadow-sm bg-white">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="fw-medium text-dark">
                                        <i class="bi bi-person-check text-primary me-1"></i>
                                        {{ $session->attendances_count }} murid tercatat
                                        @if($session->attendance_media_count > 0)
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1">
                                                <i class="bi bi-clipboard-check me-1"></i>{{ $session->attendance_media_count }} bukti
                                            </span>
                                        @endif
                                    </div>
                                    <small class="text-muted">Coach {{ $session->coach->name }}</small>
                                </div>
                                <div class="text-primary">
                                    <i class="bi bi-chevron-right"></i>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @empty
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-muted opacity-50 mb-3 d-block"></i>
                    <h6 class="text-muted mb-0">Belum ada sesi kehadiran yang sesuai filter.</h6>
                </div>
            @endforelse
        </div>
        @if($sessions->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $sessions->appends(request()->except('session_page'))->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
