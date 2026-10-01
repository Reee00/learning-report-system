@extends('layouts.app')
@section('title', 'Jadwal Mengajar')

@section('content')
@include('admin.schedules._ui')
@php
    // Dua tampilan: POLA (default) mengikuti alur Excel per hari; SESI adalah
    // daftar pertemuan tergenerate beserta filter tanggal.
    $view = $view ?? 'pola';
    $dayLabels = [1 => 'SENIN', 2 => 'SELASA', 3 => 'RABU', 4 => 'KAMIS', 5 => 'JUMAT', 6 => 'SABTU'];
    $filters = array_filter([
        'school_id' => request('school_id'),
        'class_id'  => request('class_id'),
        'coach_id'  => request('coach_id'),
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
                        <x-breadcrumb :items="[
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Jadwal Mengajar'],
            ]" />
<h1 class="page-title">Jadwal Mengajar</h1>
            <p class="text-muted small mb-0">
                @if(auth()->user()->role === \App\Models\User::ROLE_COACH)
                    Jadwal sesi mengajar Anda.
                @elseif(auth()->user()->role === \App\Models\User::ROLE_SCHOOL_PIC)
                    Jadwal mengajar di sekolah yang Anda kelola.
                @else
                    Pola jadwal berulang per hari dan sekolah, seperti sheet Excel operasional.
                @endif
            </p>
        </div>
        @if($canManage)
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.schedules.create', ['day' => $activeDay]) }}"
               class="btn btn-primary shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-plus-lg"></i> Tambah Pola Jadwal
            </a>
            <a href="{{ route('admin.schedules.templates', ['day' => $activeDay]) }}"
               class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i class="bi bi-list-task"></i> Daftar Pola
            </a>
            <a href="{{ route('admin.schedules.template') }}" class="btn btn-light border d-flex align-items-center gap-2">
                <i class="bi bi-filetype-csv"></i> Unduh Template
            </a>
            <button type="button" class="btn btn-light border d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#importScheduleModal">
                <i class="bi bi-file-earmark-arrow-up"></i> Import Excel
            </button>
        </div>
        @endif
    </div>

    {{-- Pemilih tampilan: POLA (metadata preferensi) vs SESI (tempat kerja
         sebenarnya — tanggal diisi manual di sini). --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $view === 'pola' ? 'active' : '' }}"
               href="{{ route('admin.schedules.index', array_merge(['view' => 'pola', 'day' => $activeDay], $filters)) }}">
                <i class="bi bi-diagram-3 me-1"></i> Pola per Hari
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $view === 'sesi' ? 'active' : '' }}"
               href="{{ route('admin.schedules.index', array_merge(['view' => 'sesi'], $filters)) }}">
                <i class="bi bi-calendar-check me-1"></i> Sesi / Pertemuan
            </a>
        </li>
    </ul>

@if($view === 'pola')
    {{-- ============ TAB HARI ============ --}}
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-header bg-white py-2 border-bottom border-light">
            <ul class="nav nav-pills flex-nowrap overflow-auto gap-1" id="scheduleDayTabs">
                @foreach($dayLabels as $dayNumber => $dayLabel)
                    <li class="nav-item">
                        <a class="nav-link {{ (int) $activeDay === (int) $dayNumber ? 'active' : '' }}"
                           href="{{ route('admin.schedules.index', array_merge(['view' => 'pola', 'day' => $dayNumber], $filters)) }}">
                            {{ $dayLabel }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="view" value="pola">
                <input type="hidden" name="day" value="{{ $activeDay }}">
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label text-muted small fw-semibold">Sekolah</label>
                    <select name="school_id" class="form-select">
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" {{ request('school_id') == $school->id ? 'selected' : '' }}>{{ $school->name }}</option>
                        @endforeach
                    </select>
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
                            <option value="{{ $coach->id }}" {{ request('coach_id') == $coach->id ? 'selected' : '' }}>{{ $coach->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-3 d-flex gap-2">
                    <button class="btn btn-primary px-4 flex-grow-1"><i class="bi bi-search me-1"></i> Filter</button>
                    <a href="{{ route('admin.schedules.index', ['view' => 'pola', 'day' => $activeDay]) }}"
                       class="btn btn-light border" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ============ DAFTAR POLA ============ --}}
    @forelse($patterns as $pattern)
        @php
            $school = $pattern['school'];
            $patternParams = [
                'day' => $pattern['day_of_week'],
                'school_id' => $school?->id,
                'start_date' => $pattern['start_date']?->toDateString(),
            ];
        @endphp
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div style="min-width: 0;" class="flex-grow-1">
                        <div class="fw-bold fs-6 text-dark">
                            <i class="bi bi-building text-primary me-1"></i>{{ $school->name ?? 'Sekolah' }}
                        </div>

                        {{-- Hierarki informasi: periode pola lebih dulu, lalu
                             keberangkatan sebagai informasi pendukung. --}}
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <span class="badge bg-primary-subtle text-primary-emphasis">
                                <i class="bi bi-calendar-event me-1"></i>
                                Mulai: {{ $pattern['start_date']?->translatedFormat('d M Y') ?? '—' }}
                            </span>
                            <span class="badge bg-light text-dark border">
                                {{ $pattern['meeting_count'] }} pertemuan
                            </span>
                            <span class="badge bg-light text-dark border">
                                s/d {{ $pattern['end_date']?->translatedFormat('d M Y') ?? '—' }}
                            </span>
                            <span class="badge {{ $pattern['session_count'] >= $pattern['meeting_total'] ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis' }}">
                                {{ $pattern['session_count'] }} / {{ $pattern['meeting_total'] }} sesi
                            </span>
                        </div>

                        @if($pattern['departure_location'] || $pattern['departure_time'])
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <span class="badge bg-light text-secondary border fw-normal">
                                    <i class="bi bi-bus-front me-1"></i>
                                    {{ $pattern['departure_location'] ?: '—' }}
                                    {{ $pattern['departure_time']?->format('H:i') ?? '' }}
                                    @if($pattern['arrival_time']) &rarr; {{ $pattern['arrival_time']->format('H:i') }} @endif
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="d-flex gap-1 flex-wrap">
                        <a href="{{ $pattern['detail_url'] }}" class="btn btn-sm btn-light border text-nowrap" title="Lihat pertemuan tergenerate">
                            <i class="bi bi-list-ol"></i><span class="ms-1">Detail</span>
                        </a>
                        @if($canManage)
                            <a href="{{ route('admin.schedules.create', array_merge(['day' => $pattern['day_of_week']], array_filter([
                                    'school' => $school?->id,
                                    'start' => $pattern['start_date']?->toDateString(),
                                ]))) }}"
                               class="btn btn-sm btn-light border text-nowrap" title="Buka pola ini di form">
                                <i class="bi bi-pencil"></i><span class="ms-1">Form</span>
                            </a>
                            <form method="POST" action="{{ route('admin.schedules.pattern.generate') }}">
                                @csrf
                                <input type="hidden" name="day" value="{{ $pattern['day_of_week'] }}">
                                <input type="hidden" name="school_id" value="{{ $school?->id }}">
                                <input type="hidden" name="start_date" value="{{ $pattern['start_date']?->toDateString() }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Generate sesi yang kurang">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.schedules.pattern.destroy') }}"
                                  id="deletePatternForm{{ $school?->id ?? 'all' }}-{{ $pattern['day_of_week'] }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="day" value="{{ $pattern['day_of_week'] }}">
                                <input type="hidden" name="school_id" value="{{ $school?->id }}">
                                <input type="hidden" name="start_date" value="{{ $pattern['start_date']?->toDateString() }}">
                                <input type="hidden" name="label" value="{{ $pattern['day_label'] }} — {{ $school->name ?? 'Sekolah' }}">
                                <button type="button"
                                        onclick="confirmSubmitForm('deletePatternForm{{ $school?->id ?? 'all' }}-{{ $pattern['day_of_week'] }}', 'Hapus pola {{ $pattern['day_label'] }} — {{ $school->name ?? '' }} beserta pertemuan hasil generate-nya? Pola yang pertemuannya sudah punya laporan/absensi tidak bisa dihapus.', 'btn-danger', 'Ya, Hapus')"
                                        class="btn btn-sm btn-outline-danger" title="Hapus pola" aria-label="Hapus pola {{ $pattern['day_label'] }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 sched-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Jam</th>
                            <th>Kelas</th>
                            <th>Program</th>
                            <th>Coach</th>
                            <th class="text-center">Murid</th>
                            <th class="text-center">Minggu Ini?</th>
                            <th>KET</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pattern['rows'] as $row)
                            <tr @class(['sched-row-off' => !$row->jalan_minggu_ini, 'table-warning' => !$row->jalan_minggu_ini])>
                                <td class="ps-3 sched-nw" data-label="Jam">
                                    {{ $row->start_time?->format('H:i') ?? '—' }}–{{ $row->end_time?->format('H:i') ?? '—' }}
                                </td>
                                <td class="fw-medium" data-label="Kelas">{{ $row->schoolClass->name ?? '—' }}</td>
                                <td data-label="Program">
                                    @if($row->program)
                                        <span class="badge bg-primary-subtle text-primary-emphasis">{{ $row->program->name }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td data-label="Coach">
                                    <div class="d-flex flex-wrap gap-1 justify-content-end justify-content-md-start">
                                        <span class="badge text-bg-primary">{{ $row->coach->name ?? '—' }}</span>
                                        @foreach($row->additionalCoaches as $extra)
                                            <span class="badge text-bg-secondary">{{ $extra->name }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-center sched-nw" data-label="Murid">{{ $row->student_count ?? '—' }}</td>
                                <td class="text-center" data-label="Minggu Ini?">
                                    @if($row->jalan_minggu_ini)
                                        <span class="badge bg-success-subtle text-success-emphasis">Jalan</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger-emphasis">Tidak Jalan</span>
                                    @endif
                                </td>
                                <td class="small text-muted" data-label="KET">{{ $row->keterangan ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-muted opacity-50 mb-2 d-block"></i>
                <h6 class="text-muted mb-1">Belum ada pola jadwal pada hari ini.</h6>
                <p class="small text-muted mb-3">
                    Pola dimasukkan per hari seperti sheet Excel: pilih hari, lalu tambahkan blok sekolah
                    beserta tanggal mulai dan jumlah pertemuannya.
                </p>
                @if($canManage)
                    <a href="{{ route('admin.schedules.create', ['day' => $activeDay]) }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Pola Jadwal
                    </a>
                @endif
            </div>
        </div>
    @endforelse

    @if($patternsPaginator && $patternsPaginator->hasPages())
        <div class="card shadow-sm border-0">
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $patternsPaginator->links() }}
            </div>
        </div>
    @endif

@else
    {{-- ============ SESI TERGENERATE ============ --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="view" value="sesi">
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label text-muted small fw-semibold">Sekolah</label>
                    <select name="school_id" class="form-select">
                        <option value="">Semua Sekolah</option>
                        @foreach($schools as $school)
                            <option value="{{ $school->id }}" {{ request('school_id') == $school->id ? 'selected' : '' }}>{{ $school->name }}</option>
                        @endforeach
                    </select>
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
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Program</label>
                    <select name="program_id" class="form-select">
                        <option value="">Semua Program</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" {{ request('program_id') == $program->id ? 'selected' : '' }}>{{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Coach</label>
                    <select name="coach_id" class="form-select">
                        <option value="">Semua Coach</option>
                        @foreach($coaches as $coach)
                            <option value="{{ $coach->id }}" {{ request('coach_id') == $coach->id ? 'selected' : '' }}>{{ $coach->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Hari</label>
                    <select name="day" class="form-select">
                        <option value="">Semua Hari</option>
                        @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $i => $label)
                            <option value="{{ $i + 1 }}" {{ request('day') == (string) ($i + 1) ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Dari</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Sampai</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Status Pertemuan</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="belum-dijadwalkan" {{ request('status') === 'belum-dijadwalkan' ? 'selected' : '' }}>Belum Dijadwalkan</option>
                        @foreach(\App\Models\TeachingSchedule::STATUS_LABELS as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" {{ request('status') === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                        @endforeach
                        <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif (operasional)</option>
                        <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Jam Mulai ≥</label>
                    <input type="time" name="time_from" class="form-control" value="{{ request('time_from') }}">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label text-muted small fw-semibold">Jam Mulai ≤</label>
                    <input type="time" name="time_to" class="form-control" value="{{ request('time_to') }}">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary px-4">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.schedules.index', ['view' => 'sesi']) }}" class="btn btn-light border" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="fw-bold fs-5 text-dark"><i class="bi bi-list-task text-primary me-2"></i> Sesi / Pertemuan</span>
                <span class="small text-muted ms-2">Tanggal tiap pertemuan diisi manual dan bisa dipindah kapan saja — tanpa perlu generate ulang.</span>
            </div>
            @if($canManage)
                <a href="{{ route('admin.schedules.sessions.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Pertemuan
                </a>
            @endif
        </div>

        @if($progress->isNotEmpty())
            {{-- Target & progress per kelas: "3/10 Pertemuan Terlaksana".
                 Angka progress dihitung di backend dari sesi yang sudah punya
                 laporan (status dianggap selesai) — bukan dari jumlah sesi
                 yang tergenerate. --}}
            <div class="card-body border-bottom border-light py-3">
                <div class="small fw-semibold text-muted mb-2">
                    <i class="bi bi-bullseye me-1"></i> Target &amp; progress pertemuan
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($progress as $classId => $row)
                        @php
                            $class = $classes->firstWhere('id', $classId);
                            $target = $row['target'];
                            $done = $row['terlaksana'];
                            $percent = $target ? min(100, (int) round($done / $target * 100)) : 0;
                        @endphp
                        <div class="border rounded-3 px-3 py-2 bg-light-subtle" style="min-width: 240px;">
                            <div class="d-flex justify-content-between align-items-baseline gap-2">
                                <span class="fw-semibold small text-dark">{{ $class->name ?? 'Kelas #'.$classId }}</span>
                                <span class="small text-muted">
                                    @if($target)
                                        <span class="fw-bold text-dark">{{ $done }}/{{ $target }}</span> Pertemuan Terlaksana
                                    @else
                                        <span class="fw-bold text-dark">{{ $done }}</span> pertemuan terlaksana
                                        &bull; <span class="fst-italic">target belum ditetapkan</span>
                                    @endif
                                </span>
                            </div>
                            @if($target)
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $percent }}%"></div>
                                </div>
                            @endif
                            <div class="small text-muted mt-1">
                                <span>{{ $row['scheduled'] }} sesi terjadwal</span>
                                @if($row['unscheduled'] > 0)
                                    &bull; <span class="text-warning-emphasis">{{ $row['unscheduled'] }} belum dijadwalkan</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 sched-table">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold ps-4">Pertemuan</th>
                        <th class="text-secondary fw-semibold">Tanggal</th>
                        <th class="text-secondary fw-semibold">Waktu</th>
                        <th class="text-secondary fw-semibold">Sekolah / Kelas</th>
                        <th class="text-secondary fw-semibold">Program</th>
                        <th class="text-secondary fw-semibold">Coach</th>
                        <th class="text-secondary fw-semibold text-center">Murid</th>
                        <th class="text-secondary fw-semibold">Topik / Ket.</th>
                        <th class="text-secondary fw-semibold text-center">Status</th>
                        @if($canManage)
                        <th class="text-secondary fw-semibold">Pindah Tanggal</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 130px;">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse($schedules as $schedule)
                    <tr @class([
                        'sched-row-off' => !$schedule->jalan_minggu_ini && $schedule->is_active,
                        'table-warning' => !$schedule->jalan_minggu_ini && $schedule->is_active,
                        'sched-row-inactive' => !$schedule->is_active,
                    ])>
                        <td class="ps-4 sched-nw" data-label="Pertemuan">
                            <span class="badge text-bg-light border fw-semibold">
                                {{ $schedule->meetingLabel() }}
                            </span>
                        </td>
                        {{-- Tanggal aktual sesi. Sesi yang belum bertanggal
                             ditandai jelas supaya tidak terbaca sebagai
                             pertemuan yang sudah lewat. --}}
                        <td class="sched-nw" data-label="Tanggal">
                            @if($schedule->session_date)
                                <div class="fw-medium text-dark">{{ $schedule->session_date->format('d M Y') }}</div>
                                <small class="text-muted">{{ $schedule->session_date->translatedFormat('l') }}</small>
                            @else
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                    <i class="bi bi-calendar-x me-1"></i>Belum dijadwalkan
                                </span>
                            @endif
                        </td>
                        <td class="sched-nw" data-label="Waktu">
                            @if($schedule->start_time || $schedule->end_time)
                                <span class="badge bg-light text-dark border border-secondary-subtle">
                                    <i class="bi bi-clock me-1"></i>
                                    {{ $schedule->start_time?->format('H:i') ?? '-' }} – {{ $schedule->end_time?->format('H:i') ?? '-' }}
                                </span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td data-label="Sekolah / Kelas">
                            <div class="fw-medium text-dark">{{ $schedule->school->name ?? '-' }}</div>
                            <span class="badge bg-light text-dark border border-secondary-subtle mt-1">{{ $schedule->schoolClass->name ?? '-' }}</span>
                        </td>
                        <td data-label="Program">
                            @if($schedule->program)
                                <span class="badge bg-primary-subtle text-primary">{{ $schedule->program->name }}</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        {{-- Coach Utama + Coach Pendamping ditampilkan keduanya:
                             coach tambahan harus bisa melihat siapa saja yang
                             mengajar pada sesi yang sama. --}}
                        <td data-label="Coach">
                            <div class="d-flex flex-column gap-1 align-items-end align-items-md-start">
                                <span class="badge text-bg-primary text-start">
                                    <span class="sched-coach-role">Coach Utama</span>
                                    {{ $schedule->coach->name ?? '-' }}
                                </span>
                                @foreach($schedule->additionalCoaches as $extra)
                                    <span class="badge text-bg-secondary text-start">
                                        <span class="sched-coach-role">Coach Pendamping</span>
                                        {{ $extra->name }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="text-center sched-nw" data-label="Murid">
                            {{ $schedule->student_count ?? '-' }}
                        </td>
                        <td data-label="Topik / Ket.">
                            <span class="text-muted" title="{{ $schedule->topic }} {{ $schedule->keterangan }}">
                                {{ $schedule->topic ? Str::limit($schedule->topic, 30) : ($schedule->keterangan ? Str::limit($schedule->keterangan, 30) : '-') }}
                            </span>
                            @if(!$schedule->jalan_minggu_ini && $schedule->is_active)
                                <div><span class="badge bg-warning text-dark">Tidak jalan minggu ini</span></div>
                            @endif
                        </td>
                        {{-- Status pertemuan: Terjadwal / Terlaksana / Ditunda /
                             Dibatalkan, dan Inactive bila sesinya dinonaktifkan
                             (tidak dihitung sebagai sesi mengajar aktif). --}}
                        <td class="text-center" data-label="Status">
                            @php
                                $statusStyles = [
                                    'scheduled' => ['bg-primary-subtle text-primary-emphasis', 'bi-calendar-check', 'Terjadwal'],
                                    'completed' => ['bg-success-subtle text-success-emphasis', 'bi-check-circle', 'Terlaksana'],
                                    'postponed' => ['bg-warning-subtle text-warning-emphasis', 'bi-hourglass-split', 'Ditunda'],
                                    'cancelled' => ['bg-danger-subtle text-danger-emphasis', 'bi-x-circle', 'Dibatalkan'],
                                    'inactive'  => ['bg-secondary-subtle text-secondary-emphasis', 'bi-slash-circle', 'Inactive'],
                                ];
                                $style = $statusStyles[$schedule->displayStatus()] ?? $statusStyles['scheduled'];
                            @endphp
                            <span class="badge {{ $style[0] }}">
                                <i class="bi {{ $style[1] }} me-1"></i>{{ $style[2] }}
                            </span>
                        </td>
                        @if($canManage)
                        {{-- Pindah tanggal = aksi tersendiri. Hanya tanggal yang
                             dikirim, jadi nomor pertemuan dijamin tidak berubah
                             dan tidak ada regenerate yang dijalankan. --}}
                        <td data-label="Pindah Tanggal">
                            <form method="POST" action="{{ route('admin.schedules.reschedule', $schedule) }}"
                                  class="d-flex gap-1 align-items-center">
                                @csrf
                                @method('PATCH')
                                <input type="date" name="session_date" class="form-control form-control-sm"
                                       value="{{ $schedule->session_date?->toDateString() }}"
                                       aria-label="Tanggal {{ $schedule->meetingLabel() }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Simpan tanggal">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </form>
                            <div class="form-text mb-0" style="font-size: .7rem;">
                                Kosongkan untuk menandai belum dijadwalkan.
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1 flex-wrap">
                                <form method="POST" action="{{ route('admin.schedules.status', $schedule) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="form-select form-select-sm" style="min-width: 120px;"
                                            onchange="this.form.submit()" aria-label="Status {{ $schedule->meetingLabel() }}">
                                        @foreach(\App\Models\TeachingSchedule::STATUS_LABELS as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}" {{ $schedule->status === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <form method="POST" action="{{ route('admin.schedules.toggle-active', $schedule) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="btn btn-sm {{ $schedule->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }} rounded-pill px-2"
                                            title="{{ $schedule->is_active ? 'Nonaktifkan sesi (tidak dihapus)' : 'Aktifkan kembali sesi' }}">
                                        <i class="bi {{ $schedule->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                    </button>
                                </form>
                                <a href="{{ route('admin.schedules.edit', $schedule) }}"
                                   class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.schedules.destroy', $schedule) }}"
                                      id="deleteScheduleForm{{ $schedule->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button"
                                            onclick="confirmSubmitForm('deleteScheduleForm{{ $schedule->id }}', 'Hapus {{ $schedule->meetingLabel() }} — {{ $schedule->schoolClass->name ?? '' }}? Sesi yang sudah punya laporan tidak bisa dihapus; gunakan status Dibatalkan atau nonaktifkan sesinya.', 'btn-danger', 'Ya, Hapus')"
                                            class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Hapus" aria-label="Hapus sesi {{ $schedule->meetingLabel() }}">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 11 : 9 }}" class="text-center py-5" data-label="">
                            <i class="bi bi-calendar-x fs-1 text-muted opacity-50 mb-2 d-block"></i>
                            <h6 class="text-muted mb-0">Belum ada sesi/pertemuan.{{ $canManage ? ' Tambah pertemuan, atau buat pola jadwal lalu generate sesinya.' : '' }}</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($schedules && $schedules->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $schedules->links() }}
            </div>
        @endif
    </div>
@endif
</div>

{{-- Modal Import Excel --}}
@if($canManage)
<div class="modal fade" id="importScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <form method="POST" action="{{ route('admin.schedules.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-arrow-up text-primary me-2"></i> Import Jadwal Mengajar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Dua format didukung (terdeteksi otomatis):
                    </p>
                    <ol class="small text-muted">
                        <li>
                            <strong>Template ternormalisasi</strong> (unduh di tombol Template) — kolom:
                            <code>tanggal, nama_sekolah, nama_kelas, email_coach, jam_mulai, jam_selesai, topik</code>
                            + opsional <code>program, email_coach_tambahan, jumlah_murid, tools_dk, tools_rk, jalan_minggu_ini, keterangan</code>.
                        </li>
                        <li>
                            <strong>Workbook master perusahaan</strong> — satu sheet per hari (SENIN–SABTU),
                            seperti file operasional DIGISchool. Tiap blok sekolah menjadi
                            <strong>satu pola tersendiri</strong>: tanggal mulai dibaca dari kolom
                            <strong>KET</strong> pada baris sekolah (cth. <em>"Mulai tanggal 3 Agustus 2026"</em>),
                            sehingga tiap sekolah boleh mulai di tanggal berbeda.
                            Nama sekolah/kelas/program/coach harus sudah ada di master data LRS.
                        </li>
                    </ol>
                    <div class="alert alert-info border-0 small py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Blok sekolah <strong>tanpa tanggal mulai yang valid pada KET</strong> dilewati dan
                        dilaporkan — sistem tidak mengarang tanggal.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Jumlah Pertemuan per Pola (workbook perusahaan)</label>
                        <input type="number" name="meetings" class="form-control" value="20" min="1" max="60">
                        <div class="form-text">
                            Default 20. Dipakai hanya bila blok sekolah tidak menentukan jumlahnya sendiri.
                            Isi 1 untuk hanya mengimpor minggu tersebut.
                        </div>
                    </div>
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    <div class="form-text mt-2">
                        Baris dengan data tidak lengkap, duplikat, atau referensi tidak dikenal akan dilewati (dengan pesan detail).
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('admin.schedules.template') }}" class="btn btn-light border me-auto">
                        <i class="bi bi-download me-1"></i> Template
                    </a>
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
    // Strip tab hari discroll horizontal di layar sempit; tab aktif dibawa ke
    // tengah agar tidak pernah terbuka dalam keadaan terpotong.
    document.addEventListener('DOMContentLoaded', function () {
        var active = document.querySelector('#scheduleDayTabs .nav-link.active');
        if (active && active.scrollIntoView) {
            active.scrollIntoView({ block: 'nearest', inline: 'center' });
        }
    });
</script>
@endsection
