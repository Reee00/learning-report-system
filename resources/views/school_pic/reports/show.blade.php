@extends('layouts.app')
@section('title', 'Detail Laporan')
@section('content')
<div class="container py-4">
    <x-page-header
        title="Detail Laporan #{{ $report->id }}"
        description="{{ $report->schoolClass->name ?? '' }} — {{ $report->report_date->format('d M Y') }}"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('pic.dashboard')],
            ['label' => 'Laporan'],
            ['label' => '#'.$report->id],
        ]"
    >
        <x-slot:meta>
            <a href="{{ ctx_back_url(route('pic.dashboard')) }}" class="small text-decoration-none">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
            </a>
        </x-slot:meta>
        <span class="badge bg-success">{{ ucfirst($report->status) }}</span>
    </x-page-header>

    @include('partials.accident-notes', [
        'notes' => $report->notes,
        'reportId' => $report->id,
    ])

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">Kelas</dt>    <dd class="col-sm-8">{{ $report->schoolClass->name }}</dd>
                <dt class="col-sm-4">Coach</dt>    <dd class="col-sm-8">{{ $report->coach->name }}</dd>
                <dt class="col-sm-4">Tanggal</dt>  <dd class="col-sm-8">{{ $report->report_date->format('d M Y') }}</dd>
                <dt class="col-sm-4">Materi</dt>   <dd class="col-sm-8">{{ $report->lesson_material }}</dd>
                <dt class="col-sm-4">Goals Materi</dt>
                <dd class="col-sm-8">{!! nl2br(e($report->goals_materi)) !!}</dd>
                <dt class="col-sm-4">Activity Report</dt>
                <dd class="col-sm-8">{!! nl2br(e($report->activity_report)) !!}</dd>
            </dl>
        </div>
    </div>

{{-- GALERI FOTO — preview tetap, ditambah tombol Download per foto.
     File tetap dilayani route media terotorisasi yang sama. --}}
@if($report->photos->count() > 0)
<div class="card mb-3">
    <div class="card-header fw-semibold">
        📷 Foto Kegiatan ({{ $report->photos->count() }})
    </div>
    <div class="card-body">
        <div class="row g-2">
            @foreach($report->photos as $photo)
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ $photo->url() }}" target="_blank">
                    <img src="{{ $photo->url() }}"
                         class="img-fluid rounded"
                         style="height:150px;width:100%;object-fit:cover;"
                         alt="Foto {{ $loop->iteration }}">
                </a>
                <a href="{{ $photo->downloadUrl() }}"
                   class="btn btn-sm btn-outline-success rounded-pill w-100 mt-2"
                   title="Download foto ini">
                    <i class="bi bi-download me-1"></i> Download
                </a>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- GALERI BUKTI ABSENSI (Meeting 2026-09 req. G) --}}
@if($report->attendanceMedia->count() > 0)
<div class="card mb-3">
    <div class="card-header fw-semibold">
        📋 Bukti Absensi ({{ $report->attendanceMedia->count() }})
    </div>
    <div class="card-body">
        <div class="row g-2">
            @foreach($report->attendanceMedia as $att)
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ $att->url() }}" target="_blank">
                    <img src="{{ $att->url() }}"
                         class="img-fluid rounded"
                         style="height:150px;width:100%;object-fit:cover;"
                         alt="Bukti Absensi {{ $loop->iteration }}">
                </a>
                <a href="{{ $att->downloadUrl() }}"
                   class="btn btn-sm btn-outline-success rounded-pill w-100 mt-2"
                   title="Download foto bukti absensi ini">
                    <i class="bi bi-download me-1"></i> Download
                </a>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- DAFTAR VIDEO --}}
@if($report->videos->count() > 0)
<div class="card mb-3">
    <div class="card-header fw-semibold">
        🎥 Video Kegiatan ({{ $report->videos->count() }})
    </div>
    <div class="card-body">
        @foreach($report->videos as $video)
        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <p class="small text-muted mb-0">
                    {{ $video->original_name ?? 'Video ' . $loop->iteration }}
                </p>
                <a href="{{ $video->downloadUrl() }}" class="btn btn-sm btn-outline-success rounded-pill px-3">
                    <i class="bi bi-download me-1"></i> Download Video
                </a>
            </div>
            <video controls
                   class="w-100 rounded"
                   style="max-height: 400px;">
                <source src="{{ $video->url() }}">
                Browser kamu tidak mendukung pemutar video.
            </video>
        </div>
        @endforeach
    </div>
</div>
@endif

    <div class="card mb-3">
        <div class="card-header">Absensi Siswa</div>
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Nama Siswa</th><th>Status</th></tr>
                </thead>
                <tbody>
                @foreach($report->attendances as $att)
                    <tr>
                        <td>{{ $att->student->name }}</td>
                        <td>
                            @php
                                $colors = ['present'=>'success','absent'=>'danger','sick'=>'warning','permission'=>'info'];
                                $labels = ['present'=>'Hadir','absent'=>'Absen','sick'=>'Sakit','permission'=>'Izin'];
                            @endphp
                            <span class="badge bg-{{ $colors[$att->status] }}">
                                {{ $labels[$att->status] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('pic.dashboard') }}" class="btn btn-outline-secondary">
            ← Kembali ke Dashboard
        </a>
        <a href="{{ route('pic.reports.download', $report) }}"
           target="_blank"
           id="btn-download-report"
           class="btn btn-success fw-semibold">
            <i class="bi bi-download me-2"></i> Download Report
        </a>
    </div>
</div>
@endsection
