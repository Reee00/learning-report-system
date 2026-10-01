@extends('layouts.app')
@section('title', 'Dashboard Sekolah')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <div class="bg-primary text-white rounded-3 p-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                <i class="bi bi-building fs-4"></i>
            </div>
            <div>
                <h1 class="page-title">Dashboard Sekolah</h1>
                <div class="text-muted small mt-1">
                    <i class="bi bi-geo-alt me-1"></i>
                    {{ $schools->pluck('name')->join(', ') ?: 'Belum ada sekolah terplot' }}
                </div>
            </div>
        </div>
        <a href="{{ route('attendance.export', request()->query()) }}" class="btn btn-success d-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-file-earmark-spreadsheet"></i> Unduh Data Kehadiran
        </a>
    </div>

    {{-- Statistik --}}
    <div class="row g-4 mb-4">
        <div class="col-sm-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
                <div class="card-body position-relative overflow-hidden d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted fw-semibold small text-uppercase mb-2">Total Laporan Disetujui</div>
                        <div class="fs-1 fw-bold text-primary">{{ $totalReports }}</div>
                    </div>
                    <i class="bi bi-check-circle-fill text-primary opacity-25" style="font-size: 4rem;"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                <div class="card-body position-relative overflow-hidden d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted fw-semibold small text-uppercase mb-2">Laporan Bulan Ini</div>
                        <div class="fs-1 fw-bold text-success">{{ $thisMonth }}</div>
                    </div>
                    <i class="bi bi-calendar-check-fill text-success opacity-25" style="font-size: 4rem;"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Coach yang belum menyelesaikan laporan (Meeting 2026-09 req. D) --}}
    @if($overdueCoaches->count() > 0)
    <div class="card mb-4 border-0 shadow-sm border-start border-warning border-4">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex align-items-center">
            <span class="fw-bold fs-6 text-dark"><i class="bi bi-bell-fill text-warning me-2"></i> Coach Belum Menyelesaikan Laporan</span>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-2">{{ $overdueCoaches->count() }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-secondary fw-semibold ps-4">Coach</th>
                            <th class="text-secondary fw-semibold text-center" style="width: 200px;">Sesi Tanpa Laporan</th>
                            <th class="text-secondary fw-semibold text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($overdueCoaches as $coach)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 28px; height: 28px; font-size: 12px;">
                                        {{ substr($coach->name, 0, 1) }}
                                    </div>
                                    <span class="fw-medium">{{ $coach->name }}</span>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                    {{ $coach->missing_sessions_count }} sesi
                                </span>
                            </td>
                            <td class="text-center">
                                <form method="POST" action="{{ route('pic.remind') }}"
                                      class="d-inline"
                                      onsubmit="const msg = prompt('Pesan tambahan untuk {{ $coach->name }} (opsional):'); if (msg === null) return false; this.querySelector('input[name=message]').value = msg;">
                                    @csrf
                                    <input type="hidden" name="coach_id" value="{{ $coach->id }}">
                                    <input type="hidden" name="message" value="">
                                    <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                                        <i class="bi bi-bell me-1"></i> Ingatkan
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Filter --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold">Pilih Kelas</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-easel"></i></span>
                        <select name="class_id" class="form-select border-start-0 ps-0">
                            <option value="">Semua Kelas</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>
                                    {{ $class->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                {{-- Meeting 2026-09 req. C: filter laporan berdasarkan coach yang mengajar di sekolah plot-nya --}}
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold">Pilih Coach</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-person-badge"></i></span>
                        <select name="coach_id" class="form-select border-start-0 ps-0">
                            <option value="">Semua Coach</option>
                            @foreach($coaches as $coach)
                                <option value="{{ $coach->id }}" {{ request('coach_id') == $coach->id ? 'selected' : '' }}>
                                    {{ $coach->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold">Dari Tanggal</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                        <input type="date" name="date_from" class="form-control border-start-0 ps-0" value="{{ request('date_from') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold">Sampai Tanggal</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                        <input type="date" name="date_to" class="form-control border-start-0 ps-0" value="{{ request('date_to') }}">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill d-flex justify-content-center align-items-center gap-2">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="{{ route('pic.dashboard') }}" class="btn btn-light border d-flex justify-content-center align-items-center gap-2" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel Laporan --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <span class="fw-bold fs-5 text-dark"><i class="bi bi-list-check text-primary me-2"></i> Daftar Laporan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Tanggal</th>
                        <th class="text-secondary fw-semibold">Kelas</th>
                        <th class="text-secondary fw-semibold">Coach</th>
                        <th class="text-secondary fw-semibold">Materi</th>
                        <th class="text-secondary fw-semibold text-center">Absensi</th>
                        <th class="text-secondary fw-semibold text-center" style="width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>
                            <div class="fw-medium text-dark">{{ $report->report_date->format('d M Y') }}</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border border-secondary-subtle">
                                {{ $report->schoolClass->name }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 28px; height: 28px; font-size: 12px;">
                                    {{ substr($report->coach->name, 0, 1) }}
                                </div>
                                <span class="fw-medium">{{ $report->coach->name }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="text-muted text-truncate d-inline-block" style="max-width: 250px;" title="{{ $report->lesson_material }}">
                                {{ Str::limit($report->lesson_material, 50) }}
                            </span>
                        </td>
                        <td class="text-center">
                            @php
                                $present = $report->attendances->where('status', 'present')->count();
                                $total   = $report->attendances->count();
                                $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
                                $color = $percentage == 100 ? 'success' : ($percentage > 50 ? 'warning' : 'danger');
                            @endphp
                            <span class="badge bg-{{ $color }}-subtle text-{{ $color }} border border-{{ $color }}-subtle px-2 py-1">
                                <i class="bi bi-people-fill me-1"></i> {{ $present }}/{{ $total }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <a href="{{ route('pic.reports.show', $report) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    Lihat
                                </a>
                                <a href="{{ route('pic.reports.download', $report) }}"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-success rounded-pill px-2">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-inbox text-muted opacity-50 mb-3 d-block lh-1" style="font-size: 4rem;" aria-hidden="true"></i>
                            <h6 class="text-muted mb-0">Belum ada laporan yang disetujui.</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
