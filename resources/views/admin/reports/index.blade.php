@extends('layouts.app')
@section('title', 'Semua Laporan')

@section('content')
<div class="container py-4">
    <h4 class="mb-4">📋 Semua Laporan</h4>

    {{-- Filter --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">Sekolah</label>
                    <select name="school_id" class="form-select form-select-sm">
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}"
                                {{ request('school_id') == $school->id ? 'selected' : '' }}>
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted</option>
                        <option value="approved"  {{ request('status') === 'approved'  ? 'selected' : '' }}>Approved</option>
                        <option value="rejected"  {{ request('status') === 'rejected'  ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Dari</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Sampai</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-fill">Filter</button>
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Tanggal</th>
                        <th>Coach</th>
                        <th>Sekolah / Kelas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reports as $report)
                    @php
                        $colors = ['draft'=>'secondary','submitted'=>'warning','approved'=>'success','rejected'=>'danger'];
                    @endphp
                    <tr>
                        <td>{{ $report->id }}</td>
                        <td>{{ $report->report_date->format('d M Y') }}</td>
                        <td>{{ $report->coach->name }}</td>
                        <td>
                            {{ $report->school->name }}<br>
                            <small class="text-muted">{{ $report->schoolClass->name }}</small>
                        </td>
                        <td>
                            <span class="badge bg-{{ $colors[$report->status] }}">
                                {{ ucfirst($report->status) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.reports.show', $report) }}"
                               class="btn btn-sm btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Tidak ada laporan ditemukan.</td>
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