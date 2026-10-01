@extends('layouts.app')
@section('title', 'Detail Pola Jadwal')

@section('content')
@include('admin.schedules._ui')
@php
    /** @var array<string, mixed> $pattern */
    $school = $pattern['school'];
    $startDate = $pattern['start_date'];
    $endDate = $pattern['end_date'];
    $meetingCount = (int) $pattern['meeting_count'];
    $sessionCount = (int) ($pattern['session_count'] ?? 0);
@endphp

<div class="container-fluid py-4">
    {{-- ============ HEADER ============ --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="flex-grow-1" style="min-width: 0;">
            <a href="{{ route('admin.schedules.index', ['view' => 'pola', 'day' => $pattern['day_of_week']]) }}"
               class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Kembali ke Jadwal
            </a>
            <h1 class="page-title">{{ $pattern['day_label'] }} — {{ $school->name ?? 'Sekolah' }}</h1>
            <p class="text-muted small mb-0">
                Pola jadwal berulang. Pertemuan yang sudah tergenerate tetap tersimpan
                walaupun pola ini dihapus.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if ($canManage)
                <a href="{{ route('admin.schedules.create', [
                        'day' => $pattern['day_of_week'],
                        'school' => $school->id ?? null,
                        'start' => $startDate?->toDateString(),
                    ]) }}"
                   class="btn btn-light border d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square"></i> Duplikat ke Form
                </a>
                <form method="POST" action="{{ route('admin.schedules.pattern.generate') }}">
                    @csrf
                    <input type="hidden" name="day" value="{{ $pattern['day_of_week'] }}">
                    <input type="hidden" name="school_id" value="{{ $school->id ?? '' }}">
                    <input type="hidden" name="start_date" value="{{ $startDate?->toDateString() }}">
                    <button type="submit" class="btn btn-outline-primary d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat"></i> Generate Sesi Kurang
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.schedules.pattern.destroy') }}"
                      id="deletePatternForm">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="day" value="{{ $pattern['day_of_week'] }}">
                    <input type="hidden" name="school_id" value="{{ $school->id ?? '' }}">
                    <input type="hidden" name="start_date" value="{{ $startDate?->toDateString() }}">
                    <input type="hidden" name="label"
                           value="{{ $pattern['day_label'] }} — {{ $school->name ?? 'Sekolah' }}">
                    <button type="button"
                            onclick="confirmSubmitForm('deletePatternForm', 'Hapus pola ini beserta pertemuan hasil generate-nya? Pola yang pertemuannya sudah punya laporan/absensi tidak bisa dihapus.', 'btn-danger', 'Ya, Hapus')"
                            class="btn btn-outline-danger d-inline-flex align-items-center gap-2">
                        <i class="bi bi-trash" aria-hidden="true"></i> Hapus Pola
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ============ RINGKASAN POLA ============ --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Tanggal Mulai</div>
                    <div class="fw-bold">{{ $startDate?->translatedFormat('d F Y') ?? '—' }}</div>
                    <div class="small text-muted">Pertemuan ke-1</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Jumlah Pertemuan</div>
                    <div class="fw-bold">{{ $meetingCount }} pertemuan</div>
                    <div class="small text-muted">
                        s/d {{ $endDate?->translatedFormat('d F Y') ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Sesi Tergenerate</div>
                    <div class="fw-bold">{{ $sessionCount }} / {{ $meetingCount }}</div>
                    <div class="small text-muted">
                        @if ($sessionCount >= $meetingCount)
                            Lengkap
                        @else
                            Kurang {{ $meetingCount - $sessionCount }} sesi
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Keberangkatan</div>
                    <div class="fw-bold">{{ $pattern['departure_location'] ?: '—' }}</div>
                    <div class="small text-muted">
                        {{ $pattern['departure_time']?->format('H:i') ?? '—' }}
                        &rarr; {{ $pattern['arrival_time']?->format('H:i') ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ BARIS KELAS PADA POLA ============ --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-mortarboard text-primary me-1"></i>
                Kelas pada Pola Ini ({{ $pattern['rows']->count() }})
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 sched-table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Jam</th>
                        <th>Program</th>
                        <th>Kelas</th>
                        <th class="text-center">Murid</th>
                        <th>Tools DK</th>
                        <th>Tools RK</th>
                        <th>Coach</th>
                        <th class="text-center">Minggu Ini?</th>
                        <th>KET</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pattern['rows'] as $row)
                        <tr @class(['sched-row-off' => !$row->jalan_minggu_ini])>
                            <td class="ps-3 sched-nw" data-label="Jam">
                                {{ $row->start_time?->format('H:i') ?? '—' }}–{{ $row->end_time?->format('H:i') ?? '—' }}
                            </td>
                            <td data-label="Program">{{ $row->program->name ?? '—' }}</td>
                            <td class="fw-medium" data-label="Kelas">{{ $row->schoolClass->name ?? '—' }}</td>
                            <td class="text-center sched-nw" data-label="Murid">{{ $row->student_count ?? '—' }}</td>
                            <td data-label="Tools DK">{{ $row->tools_dk ?: '—' }}</td>
                            <td data-label="Tools RK">{{ $row->tools_rk ?: '—' }}</td>
                            <td data-label="Coach">
                                <div class="d-flex flex-column gap-1 align-items-end align-items-md-start">
                                    <span class="badge bg-primary-subtle text-primary-emphasis text-start">
                                        <span class="sched-coach-role">Coach Utama</span>
                                        {{ $row->coach->name ?? '—' }}
                                    </span>
                                    @foreach ($row->additionalCoaches as $extra)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis text-start">
                                            <span class="sched-coach-role">Coach Pendamping</span>
                                            {{ $extra->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-center" data-label="Minggu Ini?">
                                @if ($row->jalan_minggu_ini)
                                    <span class="badge bg-success-subtle text-success-emphasis">Jalan</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger-emphasis">Tidak Jalan</span>
                                @endif
                            </td>
                            <td class="small" data-label="KET">{{ $row->keterangan ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ SESI TERGENERATE ============ --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-calendar-check text-primary me-1"></i>
                Pertemuan Tergenerate ({{ $sessions->count() }})
            </h6>
            <span class="small text-muted">
                Nomor pertemuan dihitung per pola, dari tanggal mulai sekolah ini.
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 sched-table">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">Pertemuan</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Kelas</th>
                        <th>Program</th>
                        <th>Coach</th>
                        <th>Status</th>
                        @if ($canManage)
                            <th class="text-center" style="width: 150px;">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr @class(['sched-row-inactive' => !$session->is_active])>
                            <td class="text-center" data-label="Pertemuan">
                                <span class="badge bg-light text-dark border">{{ $session->meeting_number ?? '—' }}</span>
                            </td>
                            <td class="sched-nw" data-label="Tanggal">
                                {{ $session->session_date?->translatedFormat('d M Y') ?? '—' }}
                            </td>
                            <td class="sched-nw" data-label="Jam">
                                {{ $session->start_time?->format('H:i') ?? '—' }}–{{ $session->end_time?->format('H:i') ?? '—' }}
                            </td>
                            <td data-label="Kelas">{{ $session->schoolClass->name ?? '—' }}</td>
                            <td data-label="Program">{{ $session->program->name ?? '—' }}</td>
                            <td data-label="Coach">
                                <div class="d-flex flex-column gap-1 align-items-end align-items-md-start">
                                    <span class="badge bg-primary-subtle text-primary-emphasis text-start">
                                        <span class="sched-coach-role">Coach Utama</span>
                                        {{ $session->coach->name ?? '—' }}
                                    </span>
                                    @foreach ($session->additionalCoaches as $extra)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis text-start">
                                            <span class="sched-coach-role">Coach Pendamping</span>
                                            {{ $extra->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td data-label="Status">
                                @if ($session->is_active)
                                    <span class="badge bg-success-subtle text-success-emphasis">
                                        <i class="bi bi-check-circle-fill me-1"></i>Aktif
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                        <i class="bi bi-slash-circle me-1"></i>Nonaktif
                                    </span>
                                @endif
                            </td>
                            @if ($canManage)
                                <td class="text-center" data-label="Aksi">
                                    <form method="POST"
                                          action="{{ route('admin.schedules.toggle-active', $session) }}"
                                          class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="btn btn-sm {{ $session->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                                title="{{ $session->is_active
                                                    ? 'Nonaktifkan sesi ini (tetap tersimpan sebagai riwayat)'
                                                    : 'Aktifkan kembali sesi ini' }}">
                                            <i class="bi {{ $session->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }} me-1"></i>
                                            {{ $session->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 8 : 7 }}" class="text-center text-muted py-4" data-label="">
                                Belum ada sesi tergenerate untuk pola ini.
                                @if ($canManage)
                                    Klik <strong>Generate Sesi Kurang</strong> di atas.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
