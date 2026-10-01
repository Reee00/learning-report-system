@extends('layouts.app')
@section('title', 'Daftar Pola Jadwal')

@section('content')
@include('admin.schedules._ui')
@php
    $activeDay = (int) ($activeDay ?? 0);
    $dayLabels = $dayLabels ?? [1 => 'SENIN', 2 => 'SELASA', 3 => 'RABU', 4 => 'KAMIS', 5 => 'JUMAT', 6 => 'SABTU'];
@endphp

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="page-title">Daftar Pola Jadwal</h1>
            <p class="text-muted small mb-0">
                Pola berulang per <strong>hari + sekolah</strong>. Setiap pola punya tanggal mulai dan
                jumlah pertemuan sendiri. Pertemuan yang sudah tergenerate ada di
                <a href="{{ route('admin.schedules.index', ['view' => 'sesi']) }}">Sesi Tergenerate</a>;
                hapus sesi individual di sana untuk libur/penyesuaian tanggal.
            </p>
        </div>
        <a href="{{ route('admin.schedules.create', $activeDay ? ['day' => $activeDay] : []) }}"
           class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Tambah Pola Jadwal
        </a>
    </div>

    {{-- Filter hari: default semua hari --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white py-2 border-bottom border-light">
            <ul class="nav nav-pills flex-nowrap overflow-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link {{ $activeDay === 0 ? 'active' : '' }}"
                       href="{{ route('admin.schedules.templates') }}">SEMUA</a>
                </li>
                @foreach($dayLabels as $dayNumber => $dayLabel)
                    <li class="nav-item">
                        <a class="nav-link {{ $activeDay === (int) $dayNumber ? 'active' : '' }}"
                           href="{{ route('admin.schedules.templates', ['day' => $dayNumber]) }}">{{ $dayLabel }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @forelse ($patterns as $pattern)
        @php
            $school = $pattern['school'];
        @endphp
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="fw-bold text-dark">
                            <span class="badge bg-primary-subtle text-primary-emphasis me-1">{{ $pattern['day_label'] }}</span>
                            <i class="bi bi-building text-primary me-1"></i>{{ $school->name ?? 'Sekolah' }}
                        </div>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <span class="badge bg-light text-dark border">
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
                    </div>
                    <div class="d-flex gap-1 flex-wrap">
                        <a href="{{ $pattern['detail_url'] }}" class="btn btn-sm btn-light border" title="Detail pola">
                            <i class="bi bi-list-ol"></i>
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
                                    class="btn btn-sm btn-outline-danger" title="Hapus pola beserta pertemuan hasil generate-nya" aria-label="Hapus pola {{ $pattern['day_label'] }}">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </form>
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
                            <th class="text-center">Minggu Ini?</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pattern['rows'] as $row)
                            <tr @class(['sched-row-off' => !$row->jalan_minggu_ini, 'table-warning' => !$row->jalan_minggu_ini])>
                                <td class="ps-3 sched-nw" data-label="Jam">
                                    {{ $row->start_time?->format('H:i') ?? '—' }}–{{ $row->end_time?->format('H:i') ?? '—' }}
                                </td>
                                <td class="fw-medium" data-label="Kelas">{{ $row->schoolClass->name ?? '—' }}</td>
                                <td data-label="Program">
                                    @if ($row->program)
                                        <span class="badge bg-primary-subtle text-primary-emphasis">{{ $row->program->name }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td data-label="Coach">
                                    <div class="d-flex flex-column gap-1 align-items-end align-items-md-start">
                                        <span class="badge text-bg-primary text-start">
                                            <span class="sched-coach-role">Coach Utama</span>
                                            {{ $row->coach->name ?? '—' }}
                                        </span>
                                        @foreach ($row->additionalCoaches as $extra)
                                            <span class="badge text-bg-secondary text-start">
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
                <h6 class="text-muted mb-1">Belum ada pola jadwal{{ $activeDay ? ' pada hari ini' : '' }}.</h6>
                <p class="small text-muted mb-3">
                    Buat lewat "Tambah Pola Jadwal" atau import workbook Excel perusahaan
                    (tanggal mulai dibaca dari kolom KET tiap blok sekolah).
                </p>
                <a href="{{ route('admin.schedules.create', $activeDay ? ['day' => $activeDay] : []) }}"
                   class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Pola Jadwal
                </a>
            </div>
        </div>
    @endforelse

    @if ($paginator && $paginator->hasPages())
        <div class="card shadow-sm border-0">
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $paginator->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
