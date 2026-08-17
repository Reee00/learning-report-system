@extends('layouts.app')
@section('title', 'Dashboard Sekolah')

@section('content')
<div class="container py-4">
    <h4 class="mb-1">📊 Dashboard Laporan Sekolah</h4>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="text-muted mb-0">{{ $schools->pluck('name')->join(', ') ?: 'Belum ada sekolah terplot' }}</p>
        <a href="{{ route('attendance.export', request()->query()) }}" class="btn btn-success btn-sm">
            Export Attendance CSV
        </a>
    </div>

    {{-- Statistik --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-primary">{{ $totalReports }}</div>
                    <div class="text-muted small">Total Laporan Disetujui</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-success">{{ $thisMonth }}</div>
                    <div class="text-muted small">Bulan Ini</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">Kelas</label>
                    <select name="class_id" class="form-select form-select-sm">
                        <option value="">Semua Kelas</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}"
                                {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-fill">Filter</button>
                    <a href="{{ route('pic.dashboard') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel Laporan --}}
    <div class="card">
        <div class="card-header fw-semibold">Daftar Laporan</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Coach</th>
                        <th>Materi</th>
                        <th>Absensi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>{{ $report->report_date->format('d M Y') }}</td>
                        <td>{{ $report->schoolClass->name }}</td>
                        <td>{{ $report->coach->name }}</td>
                        <td>{{ Str::limit($report->lesson_material, 50) }}</td>
                        <td>
                            @php
                                $present = $report->attendances->where('status', 'present')->count();
                                $total   = $report->attendances->count();
                            @endphp
                            <span class="badge bg-light text-dark border">
                                {{ $present }}/{{ $total }} hadir
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('pic.reports.show', $report) }}"
                               class="btn btn-sm btn-outline-primary">Lihat</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Belum ada laporan yang disetujui.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="card-footer">{{ $reports->links() }}</div>
        @endif
    </div>
</div>
@endsection
