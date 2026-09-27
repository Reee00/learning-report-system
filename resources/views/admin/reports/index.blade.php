@extends('layouts.app')
@section('title', 'Arsip Laporan')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-archive text-primary me-2"></i> Arsip Laporan</h4>
            <p class="text-muted small mb-0">
                Riwayat laporan per sekolah &rarr; kelas. Tampilan arsip bersifat baca-saja:
                laporan yang sudah dikirim/disetujui tidak diubah dari halaman ini, dan
                tidak ada salinan data — arsip menampilkan laporan yang sama.
            </p>
        </div>
        @if(app(\App\Services\AuthorizationService::class)->allows(auth()->user(), 'reports.remind'))
        <form method="POST" action="{{ route('admin.reports.remind') }}"
              onsubmit="return confirm('Kirim reminder ke semua coach yang belum menyelesaikan laporan sesinya?');">
            @csrf
            <input type="hidden" name="send_all" value="1">
            <button type="submit" class="btn btn-warning d-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-bell"></i> Ingatkan Coach Menunggak
            </button>
        </form>
        @endif
    </div>

    {{-- Filter arsip: sekolah → kelas → coach → status → rentang tanggal --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-3">
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
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label text-muted small fw-semibold">Kelas</label>
                    <select name="class_id" class="form-select">
                        <option value="">Semua Kelas</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>
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
                            <option value="{{ $coach->id }}" {{ request('coach_id') == $coach->id ? 'selected' : '' }}>
                                {{ $coach->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @unless($approvedOnly ?? false)
                <div class="col-12 col-md-6 col-xl-3">
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
                @endunless
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Dari Tanggal</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                        <input type="date" name="date_from" class="form-control border-start-0 ps-0" value="{{ request('date_from') }}">
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Sampai Tanggal</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                        <input type="date" name="date_to" class="form-control border-start-0 ps-0" value="{{ request('date_to') }}">
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Baris / Halaman</label>
                    <select name="per_page" class="form-select" onchange="this.form.submit()">
                        @foreach($allowedPerPage ?? [20] as $option)
                            <option value="{{ $option }}" {{ (int) request('per_page', 20) === $option ? 'selected' : '' }}>
                                {{ $option }} laporan
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-8 col-xl-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill d-flex justify-content-center align-items-center gap-2">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-light border d-flex justify-content-center align-items-center gap-2" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
            <p class="small text-muted mb-0 mt-3">
                <i class="bi bi-info-circle me-1"></i>
                Riwayat dikelompokkan per halaman. Pilih <strong>Baris / Halaman</strong> yang lebih besar
                bila ingin melihat seluruh riwayat satu sekolah dalam satu tampilan.
            </p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <span class="fw-bold fs-5 text-dark"><i class="bi bi-card-list text-primary me-2"></i> Riwayat Laporan</span>
            <span class="text-muted small ms-2">Sekolah &rarr; Kelas &rarr; laporan terbaru lebih dulu</span>
        </div>

        @php
            $statusInfo = [
                'draft'     => ['color' => 'secondary', 'icon' => 'pencil-square', 'label' => 'Draft'],
                'submitted' => ['color' => 'warning', 'icon' => 'hourglass-split', 'label' => 'Menunggu Review'],
                'approved'  => ['color' => 'success', 'icon' => 'check-circle-fill', 'label' => 'Disetujui'],
                'rejected'  => ['color' => 'danger', 'icon' => 'x-circle-fill', 'label' => 'Perlu Diperbaiki'],
            ];
        @endphp

        @if($grouped->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted opacity-50 mb-3 d-block"></i>
                <h6 class="text-muted mb-0">Tidak ada laporan ditemukan.</h6>
            </div>
        @endif

        {{-- Accordion Sekolah --}}
        <div class="accordion accordion-flush" id="schoolAccordion">
            @foreach($grouped as $schoolName => $classes)
                @php $schoolId = 'school-' . md5($schoolName); @endphp
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button {{ !$loop->first ? 'collapsed' : '' }} py-3" type="button"
                                data-bs-toggle="collapse" data-bs-target="#{{ $schoolId }}"
                                {{ !$loop->first ? 'aria-expanded="false"' : 'aria-expanded="true"' }}>
                            <i class="bi bi-building text-primary me-2"></i>
                            <span class="fw-bold">{{ $schoolName }}</span>
                            <span class="badge bg-primary-subtle text-primary rounded-pill ms-2">
                                {{ $classes->flatten()->count() }} laporan
                            </span>
                        </button>
                    </h2>
                    <div id="{{ $schoolId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                         data-bs-parent="#schoolAccordion">
                        <div class="accordion-body py-2 px-lg-4">

                            {{-- Accordion Kelas (nested) --}}
                            <div class="accordion" id="class-accordion-{{ $loop->index }}">
                                @foreach($classes as $className => $classReports)
                                    @php $classId = $schoolId . '-class-' . md5($className); @endphp
                                    <div class="accordion-item border-0 bg-light rounded-3 mb-2">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed bg-transparent py-2" type="button"
                                                    data-bs-toggle="collapse" data-bs-target="#{{ $classId }}" aria-expanded="false">
                                                <i class="bi bi-easel text-secondary me-2"></i>
                                                <span class="fw-semibold small">{{ $className }}</span>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill ms-2">
                                                    {{ $classReports->count() }} laporan
                                                </span>
                                            </button>
                                        </h2>
                                        <div id="{{ $classId }}" class="accordion-collapse collapse"
                                             data-bs-parent="#class-accordion-{{ $loop->parent->index }}">
                                            <div class="accordion-body py-2">
                                                <div class="table-responsive">
                                                    <table class="table table-hover align-middle mb-0 bg-white">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th class="text-secondary fw-semibold text-center" style="width: 50px;">#</th>
                                                                <th class="text-secondary fw-semibold">Tanggal</th>
                                                                <th class="text-secondary fw-semibold">Coach</th>
                                                                @unless($approvedOnly ?? false)
                                                                <th class="text-secondary fw-semibold">Status</th>
                                                                @endunless
                                                                <th class="text-center text-secondary fw-semibold" style="width: 130px;">Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                        @foreach($classReports as $report)
                                                            @php $info = $statusInfo[$report->status] ?? ['color' => 'secondary', 'icon' => 'circle', 'label' => ucfirst($report->status)]; @endphp
                                                            <tr>
                                                                <td class="text-center text-muted small">{{ $report->id }}</td>
                                                                <td>
                                                                    <div class="fw-medium text-dark">{{ $report->report_date->format('d M Y') }}</div>
                                                                    <small class="text-muted">{{ $report->report_date->diffForHumans() }}</small>
                                                                </td>
                                                                <td>
                                                                    <span class="fw-medium">{{ $report->coach->name }}</span>
                                                                </td>
                                                                @unless($approvedOnly ?? false)
                                                                <td>
                                                                    <span class="badge bg-{{ $info['color'] }}-subtle text-{{ $info['color'] }} border border-{{ $info['color'] }}-subtle px-2 py-1">
                                                                        <i class="bi bi-{{ $info['icon'] }} me-1"></i> {{ $info['label'] }}
                                                                    </span>
                                                                </td>
                                                                @endunless
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
                                                        @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($reports->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
</div>
@endsection