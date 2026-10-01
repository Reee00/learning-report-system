@extends('layouts.app')
@section('title', 'Attendance — ' . $report->report_date->format('d M Y'))

@section('content')
@php
    $statusIcons = [
        'present' => 'bi-check-circle-fill',
        'absent' => 'bi-x-circle-fill',
        'sick' => 'bi-thermometer-half',
        'permission' => 'bi-info-circle-fill',
    ];
    $statusColors = [
        'present' => 'success',
        'absent' => 'danger',
        'sick' => 'warning',
        'permission' => 'info',
    ];
    $statusLabels = [
        'present' => 'Hadir',
        'absent' => 'Absen',
        'sick' => 'Sakit',
        'permission' => 'Izin',
    ];
@endphp

<div class="container py-4">
    <x-breadcrumb :items="[
        ['label' => 'Attendance', 'url' => ctx_route('attendance.index', [], true)],
        ['label' => $report->school->name, 'url' => ctx_route('attendance.school', $report->school_id, true)],
        ['label' => $report->schoolClass->name, 'url' => ctx_route('attendance.class', [$report->school_id, $report->class_id], true)],
        ['label' => $report->report_date->translatedFormat('d M Y')],
    ]" />

    <a href="{{ ctx_route('attendance.class', [$report->school_id, $report->class_id], true) }}" class="btn btn-outline-secondary btn-sm mb-2">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="bg-light rounded p-2 text-center border border-secondary-subtle" style="min-width: 64px;">
                    <div class="fs-4 fw-bold text-dark lh-1">{{ $report->report_date->format('d') }}</div>
                    <div class="small text-muted text-uppercase lh-1 mt-1" style="font-size: 0.7rem;">{{ $report->report_date->translatedFormat('M Y') }}</div>
                </div>
                <div>
                    <h1 class="page-title mb-1">{{ $report->school->name }} &mdash; {{ $report->schoolClass->name }}</h1>
                    <p class="page-subtitle mb-0">
                        {{ $report->report_date->translatedFormat('l, d M Y') }} &bull; Coach {{ $report->coach->name }}
                        &bull; {{ $attendances->count() }} murid tercatat
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel kehadiran murid sesi ini. Akumulasi (TOTAL HADIR) sengaja
         TIDAK ditampilkan — hanya ada di dokumen unduh (keputusan UX
         2026-09-13). --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom border-light">
            <span class="fw-bold fs-6 text-dark"><i class="bi bi-list-check text-primary me-2"></i> Kehadiran Murid</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 180px;">Murid</th>
                        <th class="text-center">Status Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $entry)
                        @php
                            $color = $statusColors[$entry->status] ?? 'secondary';
                            $icon = $statusIcons[$entry->status] ?? 'bi-question-circle';
                            $label = $statusLabels[$entry->status] ?? ucfirst($entry->status);
                        @endphp
                        <tr>
                            <td class="fw-medium">{{ $entry->student->name ?? '-' }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }} border border-{{ $color }}-subtle px-3 py-2">
                                    <i class="bi {{ $icon }} me-1"></i> {{ $label }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted py-5">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 opacity-50"></i>
                                Tidak ada data murid pada sesi ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bukti kehadiran (foto daftar hadir, dsb.) --}}
    @if($report->attendanceMedia->count() > 0)
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom border-light">
            <span class="fw-bold fs-6 text-dark">
                <i class="bi bi-clipboard-check text-primary me-2"></i> Bukti Absensi
                <span class="badge bg-secondary rounded-pill ms-2">{{ $report->attendanceMedia->count() }}</span>
            </span>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                @foreach($report->attendanceMedia as $media)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ $media->url() }}" target="_blank" class="d-block overflow-hidden rounded-3 shadow-sm border border-light position-relative" style="height: 120px;">
                        <img src="{{ $media->url() }}" class="w-100 h-100 object-fit-cover" alt="Bukti Absensi {{ $loop->iteration }}">
                        <div class="position-absolute bottom-0 start-0 w-100 p-2 text-center" style="background: linear-gradient(transparent, rgba(0,0,0,0.7));">
                            <i class="bi bi-zoom-in text-white opacity-75"></i>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
