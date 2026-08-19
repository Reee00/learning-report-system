@extends('layouts.app')
@section('title', 'Attendance')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canExport = $authorization->allows($currentUser, 'attendance.export')
        || $authorization->allows($currentUser, 'attendance.export_csv');
@endphp

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-calendar-check text-primary me-2"></i> Data Kehadiran Siswa</h4>
            <p class="text-muted small mb-0">Pantau dan kelola data absensi siswa dari semua kelas.</p>
        </div>
        @if($canExport)
            <div class="d-flex gap-2">
                <a href="{{ route('attendance.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="btn btn-success shadow-sm d-flex align-items-center gap-2">
                    <i class="bi bi-filetype-csv"></i> Unduh CSV
                </a>
                <a href="{{ route('attendance.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="btn btn-danger shadow-sm d-flex align-items-center gap-2">
                    <i class="bi bi-filetype-pdf"></i> Unduh PDF
                </a>
            </div>
        @endif
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

    {{-- Filter Section --}}
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body p-4">
            <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-funnel-fill text-secondary me-2"></i> Filter Pencarian</h6>
            <form method="GET" action="{{ route('attendance.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold mb-1">Sekolah</label>
                    <select name="school_id" class="form-select border-0 shadow-sm">
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected(request('school_id') == $school->id)>
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold mb-1">Kelas</label>
                    <select name="class_id" class="form-select border-0 shadow-sm">
                        <option value="">Semua Kelas</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>
                                {{ $class->school->name }} - {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold mb-1">Status Absensi</label>
                    <select name="attendance_status" class="form-select border-0 shadow-sm">
                        <option value="">Semua</option>
                        <option value="present" @selected(request('attendance_status') === 'present')>Hadir</option>
                        <option value="absent" @selected(request('attendance_status') === 'absent')>Absen</option>
                        <option value="sick" @selected(request('attendance_status') === 'sick')>Sakit</option>
                        <option value="permission" @selected(request('attendance_status') === 'permission')>Izin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control border-0 shadow-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control border-0 shadow-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('attendance.index') }}" class="btn btn-light border shadow-sm px-4">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                    <button class="btn btn-primary shadow-sm px-4 fw-medium">
                        <i class="bi bi-search me-1"></i> Terapkan Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Data Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
            <span class="fw-bold fs-6 text-dark">
                Daftar Attendance
                <span class="badge bg-primary rounded-pill ms-2">{{ $attendance->total() }} Data</span>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold ps-4" style="width: 150px;">Tanggal</th>
                        <th class="text-secondary fw-semibold">Siswa</th>
                        <th class="text-secondary fw-semibold">Sekolah & Kelas</th>
                        <th class="text-secondary fw-semibold">Coach</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 120px;">Kehadiran</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 150px;">Status Laporan</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($attendance as $entry)
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded p-2 text-center border border-secondary-subtle me-3" style="min-width: 50px;">
                                    <div class="fs-5 fw-bold text-dark lh-1">{{ $entry->report->report_date->format('d') }}</div>
                                    <div class="small text-muted text-uppercase lh-1 mt-1" style="font-size: 0.7rem;">{{ $entry->report->report_date->format('M Y') }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $entry->student->name }}</div>
                        </td>
                        <td>
                            <div class="fw-medium text-dark"><i class="bi bi-building text-muted me-1"></i>{{ $entry->report->school->name }}</div>
                            <span class="badge bg-light text-dark border border-secondary-subtle mt-1">{{ $entry->report->schoolClass->name }}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 28px; height: 28px; font-size: 0.8rem;">
                                    {{ substr($entry->report->coach->name, 0, 1) }}
                                </div>
                                <span class="fw-medium text-dark small">{{ $entry->report->coach->name }}</span>
                            </div>
                        </td>
                        <td class="text-center">
                            @php
                                $statusIcons = [
                                    'present' => 'bi-check-circle-fill',
                                    'absent' => 'bi-x-circle-fill',
                                    'sick' => 'bi-thermometer-half',
                                    'permission' => 'bi-info-circle-fill'
                                ];
                                $statusColors = [
                                    'present' => 'success',
                                    'absent' => 'danger',
                                    'sick' => 'warning',
                                    'permission' => 'info'
                                ];
                                $statusLabels = [
                                    'present' => 'Hadir',
                                    'absent' => 'Absen',
                                    'sick' => 'Sakit',
                                    'permission' => 'Izin'
                                ];
                                $color = $statusColors[$entry->status] ?? 'secondary';
                                $icon = $statusIcons[$entry->status] ?? 'bi-question-circle';
                                $label = $statusLabels[$entry->status] ?? ucfirst($entry->status);
                            @endphp
                            <span class="badge bg-{{ $color }}-subtle text-{{ $color }} border border-{{ $color }}-subtle px-3 py-1">
                                <i class="bi {{ $icon }} me-1"></i> {{ $label }}
                            </span>
                        </td>
                        <td class="text-center">
                            @php
                                $reportStatusColors = [
                                    'draft' => 'secondary',
                                    'submitted' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger'
                                ];
                                $reportStatusLabels = [
                                    'draft' => 'Draft',
                                    'submitted' => 'Menunggu Review',
                                    'approved' => 'Disetujui',
                                    'rejected' => 'Perlu Diperbaiki'
                                ];
                                $rColor = $reportStatusColors[$entry->report->status] ?? 'secondary';
                                $rLabel = $reportStatusLabels[$entry->report->status] ?? ucfirst($entry->report->status);
                            @endphp
                            <span class="badge bg-{{ $rColor }} rounded-pill px-3">{{ $rLabel }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64" class="opacity-50 mb-3">
                            <h6 class="text-muted mb-0">Tidak ada data kehadiran yang sesuai filter.</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($attendance->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $attendance->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
