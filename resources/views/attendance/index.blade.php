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
        <h4>Attendance</h4>
        @if($canExport)
            <a href="{{ route('attendance.export', request()->query()) }}" class="btn btn-success">
                Export CSV
            </a>
        @endif
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">Sekolah</label>
                    <select name="school_id" class="form-select form-select-sm">
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" @selected(request('school_id') == $school->id)>
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Kelas</label>
                    <select name="class_id" class="form-select form-select-sm">
                        <option value="">Semua Kelas</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>
                                {{ $class->school->name }} - {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Status Absensi</label>
                    <select name="attendance_status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="present" @selected(request('attendance_status') === 'present')>Hadir</option>
                        <option value="absent" @selected(request('attendance_status') === 'absent')>Absen</option>
                        <option value="sick" @selected(request('attendance_status') === 'sick')>Sakit</option>
                        <option value="permission" @selected(request('attendance_status') === 'permission')>Izin</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Dari</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Sampai</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary btn-sm">Filter</button>
                    <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">
            Daftar Attendance
            <span class="badge bg-primary ms-1">{{ $attendance->total() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Sekolah / Kelas</th>
                        <th>Coach</th>
                        <th>Siswa</th>
                        <th>Status</th>
                        <th>Status Laporan</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($attendance as $entry)
                    <tr>
                        <td>{{ $entry->report->report_date->format('d M Y') }}</td>
                        <td>
                            {{ $entry->report->school->name }}<br>
                            <small class="text-muted">{{ $entry->report->schoolClass->name }}</small>
                        </td>
                        <td>{{ $entry->report->coach->name }}</td>
                        <td>{{ $entry->student->name }}</td>
                        <td>
                            @php($colors = ['present' => 'success', 'absent' => 'danger', 'sick' => 'warning', 'permission' => 'info'])
                            <span class="badge bg-{{ $colors[$entry->status] ?? 'secondary' }}">
                                {{ ucfirst($entry->status) }}
                            </span>
                        </td>
                        <td>{{ ucfirst($entry->report->status) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Tidak ada data attendance.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($attendance->hasPages())
            <div class="card-footer">{{ $attendance->links() }}</div>
        @endif
    </div>
</div>
@endsection
