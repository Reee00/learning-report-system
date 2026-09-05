@extends('layouts.app')
@section('title', 'Detail Laporan')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-file-earmark-text text-primary me-2"></i> Detail Laporan #{{ $report->id }}</h4>
            <p class="text-muted small mb-0">Lihat informasi detail dan kelola status laporan ini.</p>
        </div>
        @php
            $statusInfo = [
                'draft'     => ['color' => 'secondary', 'icon' => 'pencil-square', 'label' => 'Draft'],
                'submitted' => ['color' => 'warning', 'icon' => 'hourglass-split', 'label' => 'Menunggu Review'],
                'approved'  => ['color' => 'success', 'icon' => 'check-circle-fill', 'label' => 'Disetujui'],
                'rejected'  => ['color' => 'danger', 'icon' => 'x-circle-fill', 'label' => 'Ditolak / Revisi'],
            ];
            $info = $statusInfo[$report->status] ?? ['color' => 'secondary', 'icon' => 'circle', 'label' => ucfirst($report->status)];
        @endphp
        <span class="badge bg-{{ $info['color'] }}-subtle text-{{ $info['color'] }} border border-{{ $info['color'] }}-subtle px-3 py-2 fs-6">
            <i class="bi bi-{{ $info['icon'] }} me-1"></i> {{ $info['label'] }}
        </span>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            @include('partials.accident-notes', [
                'notes' => $report->notes,
                'reportId' => $report->id,
            ])

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <span class="fw-bold fs-6 text-dark"><i class="bi bi-info-circle text-primary me-2"></i> Informasi Utama</span>
                </div>
                <div class="card-body p-4">
                    <div class="row mb-3 pb-3 border-bottom border-light">
                        <div class="col-sm-4 text-muted small fw-semibold">Sekolah</div>
                        <div class="col-sm-8 fw-medium text-dark"><i class="bi bi-building text-muted me-2"></i> {{ $report->school->name }}</div>
                    </div>
                    <div class="row mb-3 pb-3 border-bottom border-light">
                        <div class="col-sm-4 text-muted small fw-semibold">Kelas</div>
                        <div class="col-sm-8 fw-medium text-dark"><span class="badge bg-light text-dark border">{{ $report->schoolClass->name }}</span></div>
                    </div>
                    <div class="row mb-3 pb-3 border-bottom border-light">
                        <div class="col-sm-4 text-muted small fw-semibold">Coach</div>
                        <div class="col-sm-8 fw-medium text-dark">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2 small" style="width: 24px; height: 24px;">
                                    {{ substr($report->coach->name, 0, 1) }}
                                </div>
                                {{ $report->coach->name }}
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3 pb-3 border-bottom border-light">
                        <div class="col-sm-4 text-muted small fw-semibold">Tanggal</div>
                        <div class="col-sm-8 fw-medium text-dark"><i class="bi bi-calendar-event text-muted me-2"></i> {{ $report->report_date->format('d M Y') }}</div>
                    </div>
                    <div class="row mb-3 pb-3 border-bottom border-light">
                        <div class="col-sm-4 text-muted small fw-semibold">Materi Pelajaran</div>
                        <div class="col-sm-8 fw-medium text-dark">{{ $report->lesson_material }}</div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4 text-muted small fw-semibold">Ringkasan Kegiatan</div>
                        <div class="col-sm-8 text-dark bg-light p-3 rounded-3 mt-2 mt-sm-0">
                            {!! nl2br(e($report->activity_summary)) !!}
                        </div>
                    </div>
                </div>
            </div>

            {{-- GALERI FOTO --}}
            @if($report->photos->count() > 0)
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <span class="fw-bold fs-6 text-dark">
                        <i class="bi bi-images text-primary me-2"></i> Foto Kegiatan
                        <span class="badge bg-secondary rounded-pill ms-2">{{ $report->photos->count() }}</span>
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach($report->photos as $photo)
                        <div class="col-6 col-md-4 col-lg-3">
                            <a href="{{ $photo->url() }}" target="_blank" class="d-block overflow-hidden rounded-3 shadow-sm border border-light position-relative" style="height: 120px;">
                                <img src="{{ $photo->url() }}" class="w-100 h-100 object-fit-cover" alt="Foto {{ $loop->iteration }}">
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

            {{-- DAFTAR VIDEO --}}
            @if($report->videos->count() > 0)
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <span class="fw-bold fs-6 text-dark">
                        <i class="bi bi-film text-primary me-2"></i> Video Kegiatan
                        <span class="badge bg-secondary rounded-pill ms-2">{{ $report->videos->count() }}</span>
                    </span>
                </div>
                <div class="card-body p-4">
                    @foreach($report->videos as $video)
                    <div class="mb-4 last:mb-0">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-play-circle-fill text-danger me-2"></i>
                            <span class="fw-medium text-dark">{{ $video->original_name ?? 'Video ' . $loop->iteration }}</span>
                        </div>
                        <div class="rounded-3 overflow-hidden shadow-sm bg-dark">
                            <video controls class="w-100 d-block" style="max-height: 400px; outline: none;">
                                <source src="{{ $video->url() }}">
                                Browser kamu tidak mendukung pemutar video.
                            </video>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <span class="fw-bold fs-6 text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> Absensi Siswa</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-secondary fw-semibold ps-4">Nama Siswa</th>
                                <th class="text-secondary fw-semibold text-center" style="width: 150px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($report->attendances as $att)
                            <tr>
                                <td class="fw-medium ps-4">{{ $att->student->name }}</td>
                                <td class="text-center">
                                    @php
                                        $attColors = ['present'=>'success','absent'=>'danger','sick'=>'warning','permission'=>'info'];
                                        $attLabels = ['present'=>'Hadir','absent'=>'Absen','sick'=>'Sakit','permission'=>'Izin'];
                                        $attIcons  = ['present'=>'check-circle-fill','absent'=>'x-circle-fill','sick'=>'heart-pulse-fill','permission'=>'envelope-fill'];
                                    @endphp
                                    <span class="badge bg-{{ $attColors[$att->status] }}-subtle text-{{ $attColors[$att->status] }} border border-{{ $attColors[$att->status] }}-subtle px-3 py-1">
                                        <i class="bi bi-{{ $attIcons[$att->status] }} me-1"></i> {{ $attLabels[$att->status] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Panel Approve/Reject (hanya tampil jika status submitted DAN user memiliki hak review) --}}
        @if($report->status === 'submitted' && ($canReview ?? false))
        <div class="col-md-4">
            <div class="position-sticky" style="top: 2rem;">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4 text-center">
                        <div class="mb-3">
                            <div class="bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 64px; height: 64px;">
                                <i class="bi bi-hourglass-split fs-2"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-1">Review Diperlukan</h5>
                            <p class="text-muted small mb-0">Laporan ini menunggu keputusan Anda.</p>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm border-top border-success border-4 mb-3">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-success mb-2"><i class="bi bi-check-circle-fill me-2"></i> Setujui Laporan</h6>
                        <p class="text-muted small mb-3">
                            Menyetujui laporan akan membuatnya terlihat oleh pihak sekolah.
                        </p>
                        <form method="POST" action="{{ route('admin.reports.approve', $report) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-success w-100 fw-semibold shadow-sm">Setujui Laporan</button>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm border-top border-danger border-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-danger mb-2"><i class="bi bi-x-circle-fill me-2"></i> Tolak Laporan</h6>
                        <p class="text-muted small mb-3">
                            Laporan akan dikembalikan ke coach untuk direvisi.
                        </p>
                        <form method="POST" action="{{ route('admin.reports.reject', $report) }}">
                            @csrf
                            @method('PATCH')
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary small">Alasan Penolakan <span class="text-danger">*</span></label>
                                <textarea name="admin_notes" class="form-control bg-light" rows="3" required placeholder="Jelaskan apa yang harus diperbaiki oleh coach..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger w-100 fw-semibold shadow-sm">Tolak & Minta Revisi</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="mt-4 d-flex gap-2 flex-wrap">
        <a href="{{ route('admin.reports.index') }}" class="btn btn-light border px-4 py-2 fw-medium text-secondary">
            <i class="bi bi-arrow-left me-2"></i> Kembali ke Daftar
        </a>
        @if($report->status === 'approved' && app(\App\Services\AuthorizationService::class)->allows(auth()->user(), 'reports.download'))
        <a href="{{ route('admin.reports.download', $report) }}"
           target="_blank"
           id="btn-download-report"
           class="btn btn-success px-4 py-2 fw-semibold shadow-sm">
            <i class="bi bi-download me-2"></i> Download Report
        </a>
        @endif
    </div>
</div>
@endsection
