@extends('layouts.app')
@section('title', 'Semua Laporan')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-collection text-primary me-2"></i> Semua Laporan</h4>
            <p class="text-muted small mb-0">Kelola dan pantau semua laporan dari seluruh coach dan sekolah.</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold">Sekolah</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-building"></i></span>
                        <select name="school_id" class="form-select border-start-0 ps-0">
                            <option value="">Semua Sekolah</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" {{ request('school_id') == $school->id ? 'selected' : '' }}>
                                    {{ $school->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold">Status</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-funnel"></i></span>
                        <select name="status" class="form-select border-start-0 ps-0">
                            <option value="">Semua</option>
                            <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Menunggu Review</option>
                            <option value="approved"  {{ request('status') === 'approved'  ? 'selected' : '' }}>Disetujui</option>
                            <option value="rejected"  {{ request('status') === 'rejected'  ? 'selected' : '' }}>Ditolak</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold">Dari Tanggal</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                        <input type="date" name="date_from" class="form-control border-start-0 ps-0" value="{{ request('date_from') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-semibold">Sampai Tanggal</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                        <input type="date" name="date_to" class="form-control border-start-0 ps-0" value="{{ request('date_to') }}">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary flex-fill d-flex justify-content-center align-items-center gap-2">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-light border d-flex justify-content-center align-items-center gap-2" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <span class="fw-bold fs-5 text-dark"><i class="bi bi-card-list text-primary me-2"></i> Daftar Laporan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold text-center" style="width: 50px;">#</th>
                        <th class="text-secondary fw-semibold">Tanggal</th>
                        <th class="text-secondary fw-semibold">Coach</th>
                        <th class="text-secondary fw-semibold">Sekolah / Kelas</th>
                        <th class="text-secondary fw-semibold">Status</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reports as $report)
                    @php
                        $statusInfo = [
                            'draft'     => ['color' => 'secondary', 'icon' => 'pencil-square', 'label' => 'Draft'],
                            'submitted' => ['color' => 'warning', 'icon' => 'hourglass-split', 'label' => 'Menunggu Review'],
                            'approved'  => ['color' => 'success', 'icon' => 'check-circle-fill', 'label' => 'Disetujui'],
                            'rejected'  => ['color' => 'danger', 'icon' => 'x-circle-fill', 'label' => 'Perlu Diperbaiki'],
                        ];
                        $info = $statusInfo[$report->status] ?? ['color' => 'secondary', 'icon' => 'circle', 'label' => ucfirst($report->status)];
                    @endphp
                    <tr>
                        <td class="text-center text-muted small">{{ $report->id }}</td>
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
                            <div class="fw-medium text-dark"><i class="bi bi-building text-muted me-1"></i> {{ $report->school->name }}</div>
                            <div class="small text-muted mt-1">
                                <span class="badge bg-light text-dark border border-secondary-subtle">
                                    {{ $report->schoolClass->name }}
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $info['color'] }}-subtle text-{{ $info['color'] }} border border-{{ $info['color'] }}-subtle px-2 py-1">
                                <i class="bi bi-{{ $info['icon'] }} me-1"></i> {{ $info['label'] }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                <a href="{{ route('admin.reports.show', $report) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    Detail
                                </a>
                                @if($report->status === 'approved' && app(\App\Services\AuthorizationService::class)->allows(auth()->user(), 'reports.download'))
                                <a href="{{ route('admin.reports.download', $report) }}"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-success rounded-pill px-3">
                                    <i class="bi bi-download"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64" class="opacity-50 mb-3">
                            <h6 class="text-muted mb-0">Tidak ada laporan ditemukan.</h6>
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