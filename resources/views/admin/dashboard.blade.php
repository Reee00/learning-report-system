@extends('layouts.app')
@section('title', 'Admin Dashboard')

@section('content')
<div class="container py-4">
    <h4 class="mb-4">📊 Admin Dashboard</h4>

    {{-- Statistik --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="card text-center border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <div class="fs-2 fw-bold">{{ $stats['total_reports'] }}</div>
                    <div class="small">Total Laporan</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card text-center border-0 shadow-sm bg-warning">
                <div class="card-body">
                    <div class="fs-2 fw-bold">{{ $stats['submitted_reports'] }}</div>
                    <div class="small">Menunggu Review</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card text-center border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <div class="fs-2 fw-bold">{{ $stats['approved_reports'] }}</div>
                    <div class="small">Disetujui</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card text-center border-0 shadow-sm bg-danger text-white">
                <div class="card-body">
                    <div class="fs-2 fw-bold">{{ $stats['rejected_reports'] }}</div>
                    <div class="small">Ditolak</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Laporan Yang Perlu Direview --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">⏳ Laporan Menunggu Review</span>
            <a href="{{ route('admin.reports.index', ['status' => 'submitted']) }}"
               class="btn btn-sm btn-outline-primary">Lihat Semua</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Coach</th>
                        <th>Sekolah</th>
                        <th>Kelas</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($pendingReports as $report)
                    <tr>
                        <td>{{ $report->report_date->format('d M Y') }}</td>
                        <td>{{ $report->coach->name }}</td>
                        <td>{{ $report->school->name }}</td>
                        <td>{{ $report->schoolClass->name }}</td>
                        <td>
                            <a href="{{ route('admin.reports.show', $report) }}"
                               class="btn btn-sm btn-primary">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">
                            🎉 Semua laporan sudah direview!
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection