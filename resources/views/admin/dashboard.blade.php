@extends('layouts.app')
@section('title', auth()->user()->role === 'superadmin' ? 'SuperAdmin Dashboard' : 'Relation Dashboard')

@section('content')
<div class="container">
    <div class="d-flex align-items-center mb-4">
        <div class="bg-primary text-white rounded-3 p-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
            <i class="bi bi-speedometer2 fs-4"></i>
        </div>
        <div>
            <h4 class="mb-0 fw-bold">{{ auth()->user()->role === 'superadmin' ? 'SuperAdmin' : 'Relation' }} Dashboard</h4>
            <span class="text-muted small">Ringkasan data laporan dan aktivitas sistem.</span>
        </div>
    </div>

    {{-- Statistik --}}
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
                <div class="card-body position-relative overflow-hidden">
                    <i class="bi bi-file-earmark-text position-absolute text-primary opacity-10" style="font-size: 5rem; right: -15px; bottom: -20px;"></i>
                    <div class="text-muted fw-semibold small text-uppercase mb-2">Total Laporan</div>
                    <div class="fs-1 fw-bold text-primary">{{ $stats['total_reports'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);">
                <div class="card-body position-relative overflow-hidden">
                    <i class="bi bi-hourglass-split position-absolute text-warning opacity-10" style="font-size: 5rem; right: -15px; bottom: -20px;"></i>
                    <div class="text-muted fw-semibold small text-uppercase mb-2">Menunggu Review</div>
                    <div class="fs-1 fw-bold text-warning">{{ $stats['submitted_reports'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                <div class="card-body position-relative overflow-hidden">
                    <i class="bi bi-check-circle position-absolute text-success opacity-10" style="font-size: 5rem; right: -15px; bottom: -20px;"></i>
                    <div class="text-muted fw-semibold small text-uppercase mb-2">Disetujui</div>
                    <div class="fs-1 fw-bold text-success">{{ $stats['approved_reports'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);">
                <div class="card-body position-relative overflow-hidden">
                    <i class="bi bi-x-circle position-absolute text-danger opacity-10" style="font-size: 5rem; right: -15px; bottom: -20px;"></i>
                    <div class="text-muted fw-semibold small text-uppercase mb-2">Ditolak / Revisi</div>
                    <div class="fs-1 fw-bold text-danger">{{ $stats['rejected_reports'] }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Laporan Yang Perlu Direview --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold fs-5 text-dark"><i class="bi bi-hourglass-split text-warning me-2"></i> Laporan Menunggu Review</span>
            <a href="{{ route('admin.reports.index', ['status' => 'submitted']) }}"
               class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
               Lihat Semua <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Tanggal</th>
                        <th class="text-secondary fw-semibold">Coach</th>
                        <th class="text-secondary fw-semibold">Sekolah</th>
                        <th class="text-secondary fw-semibold">Kelas</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($pendingReports as $report)
                    <tr>
                        <td>
                            <div class="fw-medium text-dark">{{ $report->report_date->format('d M Y') }}</div>
                            <small class="text-muted">{{ $report->report_date->diffForHumans() }}</small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 32px; height: 32px; font-size: 14px;">
                                    {{ substr($report->coach->name, 0, 1) }}
                                </div>
                                <span class="fw-medium">{{ $report->coach->name }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-building text-muted me-2"></i>
                                {{ $report->school->name }}
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border border-secondary-subtle">
                                {{ $report->schoolClass->name }}
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('admin.reports.show', $report) }}"
                               class="btn btn-sm btn-primary rounded-pill px-3">
                               Review
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/1077/1077114.png" alt="All Done" width="64" class="opacity-50 mb-3">
                            <h6 class="text-muted mb-0">Hore! Semua laporan sudah selesai direview.</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
