@extends('layouts.app')
@section('title', 'Review Laporan')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4 gap-3 flex-wrap">
        <div>
                        <x-breadcrumb :items="[
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Arsip Laporan', 'url' => route('admin.reports.index')],
                ['label' => 'Review'],
            ]" />
<h1 class="page-title">Review Laporan</h1>
            <p class="text-muted small mb-0">
                Antrean kerja reviewer: laporan yang menunggu keputusan dan laporan yang perlu diperbaiki coach.
                Riwayat lengkap yang sudah selesai ada di <a href="{{ route('admin.reports.index') }}">Arsip Laporan</a>.
            </p>
        </div>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-light border d-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-archive"></i> Buka Arsip Laporan
        </a>
    </div>

    {{-- Ringkasan antrean: dihitung dari scope sekolah yang sama dengan daftar
         di bawah, jadi reviewer tidak pernah melihat angka di luar wewenangnya. --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-hourglass-split fs-2 text-warning"></i>
                    <div>
                        <div class="text-muted small fw-semibold">Menunggu Review</div>
                        <div class="fs-4 fw-bold text-dark">{{ $pendingCount }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm border-start border-danger border-4 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-arrow-repeat fs-2 text-danger"></i>
                    <div>
                        <div class="text-muted small fw-semibold">Perlu Diperbaiki Coach</div>
                        <div class="fs-4 fw-bold text-dark">{{ $rejectedCount }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter review --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label text-muted small fw-semibold">Sekolah</label>
                    <select name="school_id" class="form-select">
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" {{ (string) request('school_id') === (string) $school->id ? 'selected' : '' }}>
                                {{ $school->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label text-muted small fw-semibold">Kelas</label>
                    <select name="class_id" class="form-select">
                        <option value="">Semua Kelas</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" {{ (string) request('class_id') === (string) $class->id ? 'selected' : '' }}>
                                {{ $class->school->name ?? '' }} — {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label text-muted small fw-semibold">Coach</label>
                    <select name="coach_id" class="form-select">
                        <option value="">Semua Coach</option>
                        @foreach($coaches as $coach)
                            <option value="{{ $coach->id }}" {{ (string) request('coach_id') === (string) $coach->id ? 'selected' : '' }}>
                                {{ $coach->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label text-muted small fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Antrean</option>
                        <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Menunggu Review</option>
                        <option value="rejected"  {{ request('status') === 'rejected'  ? 'selected' : '' }}>Perlu Diperbaiki</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary d-flex justify-content-center align-items-center gap-2 px-4">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="{{ route('admin.reports.review') }}" class="btn btn-light border d-flex justify-content-center align-items-center gap-2" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    @php
        $statusInfo = [
            'submitted' => ['color' => 'warning', 'icon' => 'hourglass-split', 'label' => 'Menunggu Review'],
            'rejected'  => ['color' => 'danger', 'icon' => 'x-circle-fill', 'label' => 'Perlu Diperbaiki'],
        ];
    @endphp

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <span class="fw-bold fs-5 text-dark"><i class="bi bi-list-check text-primary me-2"></i> Antrean Review</span>
            <span class="text-muted small ms-2">Laporan terbaru lebih dulu</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Tanggal</th>
                        <th class="text-secondary fw-semibold">Sekolah</th>
                        <th class="text-secondary fw-semibold">Kelas</th>
                        <th class="text-secondary fw-semibold">Coach</th>
                        <th class="text-secondary fw-semibold">Status</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reports as $report)
                    @php $info = $statusInfo[$report->status] ?? ['color' => 'secondary', 'icon' => 'circle', 'label' => ucfirst($report->status)]; @endphp
                    <tr>
                        <td>
                            <div class="fw-medium text-dark">{{ $report->report_date->format('d M Y') }}</div>
                            <small class="text-muted">{{ $report->report_date->diffForHumans() }}</small>
                        </td>
                        <td>{{ $report->school->name }}</td>
                        <td>
                            <span class="badge bg-light text-dark border border-secondary-subtle">{{ $report->schoolClass->name }}</span>
                        </td>
                        <td>{{ $report->coach->name }}</td>
                        <td>
                            <span class="badge bg-{{ $info['color'] }}-subtle text-{{ $info['color'] }} border border-{{ $info['color'] }}-subtle px-2 py-1">
                                <i class="bi bi-{{ $info['icon'] }} me-1"></i> {{ $info['label'] }}
                            </span>
                            @if($report->status === 'rejected' && $report->admin_notes)
                                <div class="mt-2 p-2 bg-danger-subtle text-danger border border-danger-subtle rounded small">
                                    <strong><i class="bi bi-exclamation-triangle-fill"></i> Catatan:</strong><br>
                                    {{ $report->admin_notes }}
                                </div>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center flex-wrap">
                                <a href="{{ route('admin.reports.show', $report) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    Detail
                                </a>
                                @if($report->status === 'submitted')
                                    <form method="POST" action="{{ route('admin.reports.approve', $report) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">
                                            <i class="bi bi-check-lg"></i> Setujui
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3"
                                            data-bs-toggle="modal" data-bs-target="#rejectModal{{ $report->id }}">
                                        <i class="bi bi-x-lg"></i> Tolak
                                    </button>
                                @endif
                            </div>

                            @if($report->status === 'submitted')
                                <div class="modal fade" id="rejectModal{{ $report->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content text-start">
                                            <form method="POST" action="{{ route('admin.reports.reject', $report) }}">
                                                @csrf
                                                @method('PATCH')
                                                <div class="modal-header">
                                                    <h6 class="modal-title fw-bold">Tolak Laporan #{{ $report->id }}</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="text-muted small">
                                                        {{ $report->school->name }} — {{ $report->schoolClass->name }}
                                                        &bull; {{ $report->coach->name }}
                                                        &bull; {{ $report->report_date->format('d M Y') }}
                                                    </p>
                                                    <label class="form-label fw-semibold text-secondary small">Alasan Penolakan <span class="text-danger">*</span></label>
                                                    <textarea name="admin_notes" class="form-control bg-light" rows="3" required
                                                              placeholder="Jelaskan apa yang harus diperbaiki oleh coach..."></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-danger fw-semibold">Tolak &amp; Minta Revisi</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="bi bi-check2-circle fs-1 text-success opacity-75 mb-3 d-block"></i>
                            <h6 class="text-muted mb-0">Tidak ada laporan yang menunggu review.</h6>
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
