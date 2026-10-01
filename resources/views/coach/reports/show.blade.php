@extends('layouts.app')
@section('title', 'Detail Laporan')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
                        <x-breadcrumb :items="[
                ['label' => 'Laporan Saya', 'url' => route('coach.reports.index')],
                ['label' => '#'. $report->id],
            ]" />
<h1 class="page-title">Detail Laporan #{{ $report->id }}</h1>
            <p class="text-muted small mb-0">Laporan yang Anda buat untuk sesi pembelajaran ini.</p>
        </div>
        @php
            $statusInfo = [
                'draft'     => ['color' => 'secondary', 'icon' => 'pencil-square', 'label' => 'Draft / Belum Submit'],
                'submitted' => ['color' => 'warning', 'icon' => 'hourglass-split', 'label' => 'Menunggu Review'],
                'approved'  => ['color' => 'success', 'icon' => 'check-circle-fill', 'label' => 'Disetujui'],
                'rejected'  => ['color' => 'danger', 'icon' => 'x-circle-fill', 'label' => 'Perlu Diperbaiki'],
            ];
            $info = $statusInfo[$report->status] ?? ['color' => 'secondary', 'icon' => 'circle', 'label' => ucfirst($report->status)];
        @endphp
        <span class="badge bg-{{ $info['color'] }}-subtle text-{{ $info['color'] }} border border-{{ $info['color'] }}-subtle px-3 py-2 fs-6">
            <i class="bi bi-{{ $info['icon'] }} me-1"></i> {{ $info['label'] }}
        </span>
    </div>

    <div class="row g-4">
        <div class="col-md-{{ ($report->status === 'submitted' || $report->status === 'rejected') ? '8' : '12' }}">
            {{-- Accident Notes ditampilkan hanya untuk laporan MILIK SENDIRI.
                 Ini isi laporan, bukan item notification center: tidak ada
                 database notification, web push, atau notifikasi apa pun yang
                 dibuat darinya. --}}
            @if((int) $report->coach_id === (int) auth()->id())
                @include('partials.accident-notes', [
                    'notes' => $report->notes,
                    'reportId' => $report->id,
                ])
            @endif

            @if($report->status === 'rejected' && $report->admin_notes)
                <div class="card border-danger-subtle shadow-sm border-0 mb-4">
                    <div class="card-body bg-danger-subtle text-danger rounded-3">
                        <strong><i class="bi bi-exclamation-triangle-fill"></i> Catatan Admin:</strong><br>
                        {{ $report->admin_notes }}
                    </div>
                </div>
            @endif

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
                                @if((int) $report->coach_id !== (int) auth()->id())
                                    <span class="badge bg-light text-secondary border ms-2">Coach pendamping</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3 pb-3 border-bottom border-light">
                        <div class="col-sm-4 text-muted small fw-semibold">Tanggal</div>
                        <div class="col-sm-8 fw-medium text-dark"><i class="bi bi-calendar-event text-muted me-2"></i> {{ $report->report_date->format('d M Y') }}</div>
                    </div>

                    {{-- MATERI PELAJARAN
                         Nilainya diambil APA ADANYA dari kolom reports.lesson_material
                         — yaitu isian field "Materi Pelajaran" pada form laporan coach.
                         Tidak ada pencarian ulang ke jadwal, kelas, atau program.
                         Nomor "Pertemuan" hanya LABEL tampilan dari nomor pertemuan
                         sesi yang memang sudah tertaut ke laporan ini
                         (reports.teaching_schedule_id), dan tidak ditempel dua kali
                         bila materi sudah memuatnya. --}}
                    @php
                        $meetingNumber = $report->teachingSchedule?->meeting_number;
                        $material = (string) $report->lesson_material;
                        $materialLabel = ($meetingNumber && ! preg_match('/^\s*(Pertemuan|Week)\b/i', $material))
                            ? 'Pertemuan ' . $meetingNumber . ' — ' . $material
                            : $material;
                    @endphp
                    <div class="row mb-3 pb-3 border-bottom border-light">
                        <div class="col-sm-4 text-muted small fw-semibold">Materi Pelajaran</div>
                        <div class="col-sm-8">
                            <div class="fw-semibold text-dark bg-primary-subtle border border-primary-subtle rounded-3 p-3">
                                <i class="bi bi-journal-text text-primary me-2"></i>{{ $materialLabel }}
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3 pb-3 border-bottom border-light">
                        <div class="col-sm-4 text-muted small fw-semibold">Goals Materi</div>
                        <div class="col-sm-8 text-dark bg-light p-3 rounded-3 mt-2 mt-sm-0">
                            {!! nl2br(e($report->goals_materi)) !!}
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4 text-muted small fw-semibold">Activity Report</div>
                        <div class="col-sm-8 text-dark bg-light p-3 rounded-3 mt-2 mt-sm-0">
                            {!! nl2br(e($report->activity_report)) !!}
                        </div>
                    </div>
                </div>
            </div>

            {{-- GALERI FOTO — preview tetap seperti semula, ditambah tombol
                 Download per foto (route media terotorisasi yang sama). --}}
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

            {{-- GALERI BUKTI ABSENSI --}}
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
                        @foreach($report->attendanceMedia as $att)
                        <div class="col-6 col-md-4 col-lg-3">
                            <a href="{{ $att->url() }}" target="_blank" class="d-block overflow-hidden rounded-3 shadow-sm border border-light position-relative" style="height: 120px;">
                                <img src="{{ $att->url() }}" class="w-100 h-100 object-fit-cover" alt="Bukti Absensi {{ $loop->iteration }}">
                                <div class="position-absolute bottom-0 start-0 w-100 p-2 text-center" style="background: linear-gradient(transparent, rgba(0,0,0,0.7));">
                                    <i class="bi bi-zoom-in text-white opacity-75"></i>
                                </div>
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

            {{-- DAFTAR VIDEO — player + tombol Download Video --}}
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
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-medium text-dark">
                                <i class="bi bi-play-circle-fill text-danger me-2"></i>{{ $video->original_name ?? 'Video ' . $loop->iteration }}
                            </span>
                            <a href="{{ $video->downloadUrl() }}" class="btn btn-sm btn-outline-success rounded-pill px-3">
                                <i class="bi bi-download me-1"></i> Download Video
                            </a>
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

        {{-- Panel aksi: hanya untuk laporan milik sendiri. Laporan sesi bersama
             milik coach utama tetap boleh dibaca, tetapi tidak boleh diubah. --}}
        @if($report->status === 'submitted' || $report->status === 'rejected')
        <div class="col-md-4">
            <div class="position-sticky" style="top: 2rem;">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-info-circle text-primary me-2"></i> Status Laporan</h6>
                        <p class="text-muted small mb-0">
                            @if($report->status === 'submitted')
                                Laporan ini sedang menunggu review. Perbaikan hanya bisa dilakukan bila laporan dikembalikan (revisi).
                            @else
                                Laporan dikembalikan untuk diperbaiki. Silakan koreksi lalu kirim ulang.
                            @endif
                        </p>
                    </div>
                </div>

                @if((int) $report->coach_id === (int) auth()->id() && $report->status === 'rejected')
                <div class="card border-0 shadow-sm border-top border-primary border-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-pencil-square me-2"></i> Perbaiki Laporan</h6>
                        <p class="text-muted small mb-3">Buka formulir, koreksi isinya, lalu kirim ulang untuk direview.</p>
                        <a href="{{ route('coach.reports.edit', $report) }}" class="btn btn-primary w-100 fw-semibold shadow-sm">
                            Edit & Kirim Ulang
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div class="mt-4 d-flex gap-2 flex-wrap">
        <a href="{{ route('coach.reports.index') }}" class="btn btn-light border px-4 py-2 fw-medium text-secondary">
            <i class="bi bi-arrow-left me-2"></i> Kembali ke Laporan Saya
        </a>
        @if($report->status === 'approved')
        <a href="{{ route('coach.reports.download', $report) }}"
           target="_blank"
           id="btn-download-report"
           class="btn btn-success px-4 py-2 fw-semibold shadow-sm">
            <i class="bi bi-download me-2"></i> Download Report
        </a>
        @endif
    </div>
</div>
@endsection
