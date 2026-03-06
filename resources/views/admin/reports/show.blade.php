@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Detail Laporan #{{ $report->id }}</h4>
        @php
            $colors = ['draft'=>'secondary','submitted'=>'warning','approved'=>'success','rejected'=>'danger'];
        @endphp
        <span class="badge bg-{{ $colors[$report->status] }} fs-6">
            {{ ucfirst($report->status) }}
        </span>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Sekolah</dt>    <dd class="col-sm-8">{{ $report->school->name }}</dd>
                        <dt class="col-sm-4">Kelas</dt>      <dd class="col-sm-8">{{ $report->schoolClass->name }}</dd>
                        <dt class="col-sm-4">Coach</dt>      <dd class="col-sm-8">{{ $report->coach->name }}</dd>
                        <dt class="col-sm-4">Tanggal</dt>    <dd class="col-sm-8">{{ $report->report_date->format('d M Y') }}</dd>
                        <dt class="col-sm-4">Materi</dt>     <dd class="col-sm-8">{{ $report->lesson_material }}</dd>
                        <dt class="col-sm-4">Kegiatan</dt>   <dd class="col-sm-8">{{ $report->activity_summary }}</dd>
                        @if($report->notes)
                        <dt class="col-sm-4">Catatan</dt>    <dd class="col-sm-8">{{ $report->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

{{-- GALERI FOTO --}}
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
            <p class="small text-muted mb-1">
                {{ $video->original_name ?? 'Video ' . $loop->iteration }}
            </p>
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
                                        $attColors = ['present'=>'success','absent'=>'danger','sick'=>'warning','permission'=>'info'];
                                        $attLabels = ['present'=>'Hadir','absent'=>'Absen','sick'=>'Sakit','permission'=>'Izin'];
                                    @endphp
                                    <span class="badge bg-{{ $attColors[$att->status] }}">
                                        {{ $attLabels[$att->status] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Panel Approve/Reject (hanya tampil jika status submitted) --}}
        @if($report->status === 'submitted')
        <div class="col-md-4">
            <div class="card border-success mb-3">
                <div class="card-header bg-success text-white">✅ Setujui Laporan</div>
                <div class="card-body">
                    <p class="text-muted small">
                        Menyetujui laporan akan membuatnya terlihat oleh School PIC.
                    </p>
                    <form method="POST" action="{{ route('admin.reports.approve', $report) }}">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-success w-100">Setujui</button>
                    </form>
                </div>
            </div>

            <div class="card border-danger">
                <div class="card-header bg-danger text-white">❌ Tolak Laporan</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.reports.reject', $report) }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label">
                                Alasan Penolakan <span class="text-danger">*</span>
                            </label>
                            <textarea name="admin_notes"
                                      class="form-control"
                                      rows="3"
                                      required
                                      placeholder="Beritahu coach apa yang harus diperbaiki..."></textarea>
                        </div>
                        <button class="btn btn-danger w-100">Tolak</button>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>

    <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary mt-2">
        ← Kembali ke Daftar
    </a>
</div>
@endsection