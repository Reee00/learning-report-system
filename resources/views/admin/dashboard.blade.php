@extends('layouts.app')
@section('title', auth()->user()->role === 'superadmin' ? 'SuperAdmin Dashboard' : 'Relation Dashboard')

@section('content')
<div class="container-fluid">
    {{-- Page Header --}}
    <div class="d-flex align-items-center mb-4 pb-2 border-bottom border-light">
        <div class="bg-primary text-white rounded-4 p-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;">
            <i class="bi bi-grid-fill fs-4"></i>
        </div>
        <div>
            <h4 class="mb-1 fw-bold text-dark">{{ auth()->user()->role === 'superadmin' ? 'SuperAdmin' : 'Relation' }} Dashboard</h4>
            <span class="text-muted small">Ringkasan operasional dan aktivitas laporan belajar</span>
        </div>
    </div>

    {{-- Statistik (Gestalt: Common Region & Proximity) --}}
    <div class="row g-4 mb-5">
        {{-- Total Laporan --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 overflow-hidden" style="background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%);">
                <div class="card-body position-relative p-4">
                    <i class="bi bi-file-earmark-text position-absolute opacity-10" style="font-size: 8rem; right: -20px; bottom: -30px; color: var(--primary);"></i>
                    <div class="text-primary fw-semibold small text-uppercase mb-2 tracking-wide">Total Laporan</div>
                    <div class="display-5 fw-bold text-dark mb-1">{{ $stats['total_reports'] }}</div>
                    <div class="small text-muted">Semua laporan sistem</div>
                </div>
            </div>
        </div>
        {{-- Menunggu Review --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 overflow-hidden" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);">
                <div class="card-body position-relative p-4">
                    <i class="bi bi-hourglass-split position-absolute opacity-10" style="font-size: 8rem; right: -20px; bottom: -30px; color: var(--warning);"></i>
                    <div class="text-warning fw-semibold small text-uppercase mb-2 tracking-wide" style="color: #b45309 !important;">Menunggu Review</div>
                    <div class="display-5 fw-bold text-dark mb-1">{{ $stats['submitted_reports'] }}</div>
                    <div class="small text-muted">Perlu tindakan Anda</div>
                </div>
            </div>
        </div>
        {{-- Disetujui --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 overflow-hidden" style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);">
                <div class="card-body position-relative p-4">
                    <i class="bi bi-check-circle position-absolute opacity-10" style="font-size: 8rem; right: -20px; bottom: -30px; color: var(--success);"></i>
                    <div class="text-success fw-semibold small text-uppercase mb-2 tracking-wide" style="color: #166534 !important;">Disetujui</div>
                    <div class="display-5 fw-bold text-dark mb-1">{{ $stats['approved_reports'] }}</div>
                    <div class="small text-muted">Laporan valid dan selesai</div>
                </div>
            </div>
        </div>
        {{-- Ditolak --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 border-0 overflow-hidden" style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);">
                <div class="card-body position-relative p-4">
                    <i class="bi bi-x-circle position-absolute opacity-10" style="font-size: 8rem; right: -20px; bottom: -30px; color: var(--danger);"></i>
                    <div class="text-danger fw-semibold small text-uppercase mb-2 tracking-wide" style="color: #991b1b !important;">Ditolak / Revisi</div>
                    <div class="display-5 fw-bold text-dark mb-1">{{ $stats['rejected_reports'] }}</div>
                    <div class="small text-muted">Perlu perbaikan coach</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Laporan Yang Perlu Direview --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-4 border-bottom-0">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-clipboard-data text-primary me-2"></i> Menunggu Review</h5>
                <span class="text-muted small">Laporan terbaru yang dikirimkan oleh coach dan membutuhkan persetujuan.</span>
            </div>
            <a href="{{ route('admin.reports.index', ['status' => 'submitted']) }}" class="btn btn-light btn-sm text-primary fw-medium px-3 py-2">
               Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="table-responsive px-4 pb-4">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="width: 20%;">Tanggal Laporan</th>
                        <th style="width: 25%;">Coach</th>
                        <th style="width: 25%;">Sekolah & Kelas</th>
                        <th style="width: 15%;">Status</th>
                        <th class="text-end" style="width: 15%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($pendingReports as $report)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $report->report_date->format('d M Y') }}</div>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $report->report_date->diffForHumans() }}</small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary-light text-primary rounded-3 d-flex align-items-center justify-content-center me-3 fw-bold" style="width: 40px; height: 40px; background-color: var(--primary-light);">
                                    {{ substr($report->coach->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $report->coach->name }}</div>
                                    <div class="small text-muted">ID: {{ $report->coach->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-medium text-dark"><i class="bi bi-building me-2 text-muted"></i>{{ $report->school->name }}</div>
                            <span class="badge bg-light text-secondary border mt-1 px-2 py-1"><i class="bi bi-easel me-1"></i>{{ $report->schoolClass->name }}</span>
                        </td>
                        <td>
                            <span class="badge" style="background-color: var(--warning-light); color: #b45309;">Menunggu Review</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.reports.show', $report) }}" class="btn btn-sm btn-outline-primary px-3 rounded-pill">
                               Review
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="d-flex flex-column align-items-center justify-content-center">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                    <i class="bi bi-check2-all text-success" style="font-size: 2.5rem;"></i>
                                </div>
                                <h5 class="fw-bold text-dark">Semua Selesai!</h5>
                                <p class="text-muted mb-0">Tidak ada laporan yang menunggu review saat ini.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
