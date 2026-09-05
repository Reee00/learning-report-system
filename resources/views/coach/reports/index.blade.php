@extends('layouts.app')
@section('title', 'Laporan Saya')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-collection text-primary me-2"></i> Laporan Saya</h4>
            <p class="text-muted small mb-0">Kelola dan pantau status laporan yang Anda buat.</p>
        </div>
        <a href="{{ route('coach.reports.create') }}" class="btn btn-primary shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-plus-lg"></i> Submit Laporan Baru
        </a>
    </div>

    @foreach($reports as $report)
        @include('partials.accident-notes', [
            'notes' => $report->notes,
            'reportId' => $report->id,
        ])
    @endforeach

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <span class="fw-bold text-dark fs-6"><i class="bi bi-list-task text-primary me-2"></i> Riwayat Laporan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold">Tanggal</th>
                        <th class="text-secondary fw-semibold">Sekolah</th>
                        <th class="text-secondary fw-semibold">Kelas</th>
                        <th class="text-secondary fw-semibold">Status</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>
                            <div class="fw-medium text-dark">{{ $report->report_date->format('d M Y') }}</div>
                            <small class="text-muted">{{ $report->report_date->diffForHumans() }}</small>
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
                        <td>
                            @php
                                $statusInfo = [
                                    'draft'     => ['color' => 'secondary', 'icon' => 'pencil-square', 'label' => 'Draft / Belum Submit'],
                                    'submitted' => ['color' => 'warning', 'icon' => 'hourglass-split', 'label' => 'Menunggu Review'],
                                    'approved'  => ['color' => 'success', 'icon' => 'check-circle-fill', 'label' => 'Disetujui'],
                                    'rejected'  => ['color' => 'danger', 'icon' => 'x-circle-fill', 'label' => 'Perlu Diperbaiki'],
                                ];
                                $info = $statusInfo[$report->status];
                            @endphp
                            <span class="badge bg-{{ $info['color'] }}-subtle text-{{ $info['color'] }} border border-{{ $info['color'] }}-subtle px-2 py-1">
                                <i class="bi bi-{{ $info['icon'] }} me-1"></i> {{ $info['label'] }}
                            </span>

                            {{-- Tampilkan alasan penolakan --}}
                            @if($report->status === 'rejected' && $report->admin_notes)
                                <div class="mt-2 p-2 bg-danger-subtle text-danger border border-danger-subtle rounded small">
                                    <strong><i class="bi bi-exclamation-triangle-fill"></i> Catatan Admin:</strong><br>
                                    {{ $report->admin_notes }}
                                </div>
                            @endif
                        </td>
                        <td class="text-center">
                            @if(in_array($report->status, ['draft', 'rejected']))
                                <a href="{{ route('coach.reports.edit', $report) }}"
                                   class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </a>
                            @elseif($report->status === 'approved')
                                <a href="{{ route('coach.reports.download', $report) }}"
                                   target="_blank"
                                   id="btn-download-report-{{ $report->id }}"
                                   class="btn btn-sm btn-outline-success rounded-pill px-3">
                                    <i class="bi bi-download me-1"></i> Download
                                </a>
                            @else
                                <span class="text-muted small"><i class="bi bi-lock-fill"></i> Terkunci</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64" class="opacity-50 mb-3">
                            <h6 class="text-muted mb-2">Belum ada laporan.</h6>
                            <p class="text-muted small mb-3">Klik tombol di bawah untuk membuat laporan pertama Anda.</p>
                            <a href="{{ route('coach.reports.create') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="bi bi-plus"></i> Submit Laporan Baru
                            </a>
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
