@extends('layouts.app')
@section('title', 'Jadwal DIGISchool')

@section('content')
@include('admin.schedules._ui')
@php
    $dayLabels = $dayLabels ?? [1 => 'SENIN', 2 => 'SELASA', 3 => 'RABU', 4 => 'KAMIS', 5 => 'JUMAT', 6 => 'SABTU'];
@endphp

<div class="container-fluid py-4">
    {{-- ============ HEADER ============ --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="flex-grow-1" style="min-width: 0;">
            <a href="{{ route('admin.schedules.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Daftar Jadwal
            </a>
            <h4 class="mb-1 fw-bold">
                <i class="bi bi-building-gear text-primary me-2"></i>Jadwal DIGISchool
            </h4>
            <p class="text-muted small mb-0">
                Satu tab = satu hari, seperti satu sheet pada Excel operasional.
                Setiap sekolah punya <strong>tanggal mulai</strong> dan
                <strong>jumlah pertemuan</strong> sendiri.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <h6 class="mb-0 fw-bold">Beberapa blok sekolah belum benar</h6>
            </div>
            <p class="small mb-2">Tidak ada jadwal yang tersimpan. Perbaiki hal berikut lalu simpan ulang.</p>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($editing)
        <div class="alert alert-info border-0 shadow-sm">
            <i class="bi bi-pencil-square me-1"></i>
            Menyunting pola <strong>{{ $editing['day_label'] }} — {{ $editing['school']->name ?? 'Sekolah' }}</strong>
            (mulai {{ $editing['start_date']?->translatedFormat('d F Y') }}).
            Simpan sebagai pola baru pada hari yang dipilih, lalu hapus pola lama bila sudah tidak dipakai —
            sesi yang sudah tergenerate tetap tersimpan.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.schedules.bulk.store') }}" id="bulkScheduleForm">
        @csrf

        {{-- ============ ATURAN OPERASIONAL ============ --}}
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-12 col-lg-7">
                        <div class="alert alert-warning border-0 mb-0 py-2 px-3 small">
                            <i class="bi bi-clock-history me-1"></i>
                            <strong>Datang 30 Menit Sebelum Kelas di Mulai</strong>
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="small text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Tanggal mulai diambil dari kolom <strong>KET</strong> pada Excel
                            (cth. <em>"Mulai tanggal 3 Agustus 2026"</em>). Tanggal itu harus jatuh
                            pada hari yang dipilih — sistem tidak menebak tanggal.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ NAVIGASI HARI ============ --}}
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white py-2 border-bottom border-light">
                <ul class="nav nav-pills flex-nowrap overflow-auto gap-1" id="dayTabs" role="tablist">
                    @foreach($dayLabels as $dayNumber => $dayLabel)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ (int) $activeDay === (int) $dayNumber ? 'active' : '' }}"
                                    id="dayTab{{ $dayNumber }}"
                                    data-bs-toggle="pill"
                                    data-bs-target="#dayPanel{{ $dayNumber }}"
                                    data-day="{{ $dayNumber }}"
                                    type="button" role="tab">
                                {{ $dayLabel }}
                                <span class="badge bg-secondary-subtle text-secondary ms-1 d-none"
                                      id="dayCount{{ $dayNumber }}">0</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="copyDayPattern">
                        <i class="bi bi-files me-1"></i> Copy Pola Hari
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAllDays">
                        <i class="bi bi-arrows-collapse me-1"></i> Ringkas / Buka Semua Hari
                    </button>
                </div>

                <div class="tab-content" id="dayTabContent">
                    @foreach($dayLabels as $dayNumber => $dayLabel)
                        <div class="tab-pane fade {{ (int) $activeDay === (int) $dayNumber ? 'show active' : '' }}"
                             id="dayPanel{{ $dayNumber }}" role="tabpanel" data-day-panel="{{ $dayNumber }}">
                            {{-- Tidak ada input hari tersembunyi: kunci array `days`
                                 adalah nomor hari ISO, jadi hari tidak bisa tidak
                                 sinkron dengan posisinya. --}}

                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <h6 class="fw-bold mb-0">
                                    <i class="bi bi-calendar-week text-primary me-1"></i>
                                    {{ $dayLabel }}
                                </h6>
                                <button type="button" class="btn btn-sm btn-primary" data-add-school="{{ $dayNumber }}">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Sekolah
                                </button>
                            </div>

                            <div class="school-blocks" data-blocks-container="{{ $dayNumber }}"></div>

                            <div class="sched-empty text-center py-4"
                                 data-empty-state="{{ $dayNumber }}">
                                <i class="bi bi-building-add fs-2 text-muted opacity-50 d-block mb-2"></i>
                                <p class="small text-muted mb-2">Belum ada sekolah pada hari {{ $dayLabel }}.</p>
                                <button type="button" class="btn btn-sm btn-primary" data-add-school="{{ $dayNumber }}">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Sekolah
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ============ AKSI ============ --}}
        {{-- Menempel di bawah viewport supaya Simpan/Batal selalu terjangkau
             walau halaman sudah panjang. .action-bar dari app shell yang
             mengatur penumpukan tombol di layar sempit. --}}
        <div class="sched-actionbar d-flex justify-content-between align-items-center flex-wrap gap-3 action-bar">
            <div class="small text-muted">
                <i class="bi bi-info-circle me-1"></i>
                <span data-total-count>0 kelas siap disimpan</span>
                <span class="d-none d-lg-inline">
                    — baris tanpa kelas atau coach utama akan dilewati, dan bila ada satu blok
                    bermasalah tidak ada pola yang tersimpan.
                </span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('admin.schedules.index') }}" class="btn btn-light border px-4">Batal</a>
                <button type="submit" class="btn btn-primary px-4 fw-medium">
                    <i class="bi bi-check-lg me-1"></i> Simpan Semua Jadwal
                </button>
            </div>
        </div>
    </form>
</div>

{{-- ============ TEMPLATE BLOK SEKOLAH ============ --}}
<template id="schoolBlockTemplate">
    <div class="card border border-light-subtle shadow-sm mb-3 school-block">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold small text-dark">
                <i class="bi bi-building text-primary me-1"></i>
                <span data-block-title>Sekolah</span>
            </span>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-light border text-nowrap" data-duplicate-block title="Duplikat blok sekolah">
                    <i class="bi bi-files"></i><span class="ms-1">Duplikat Blok</span>
                </button>
                <button type="button" class="btn btn-sm btn-light border text-danger text-nowrap" data-remove-block title="Hapus blok">
                    <i class="bi bi-trash"></i><span class="ms-1">Hapus</span>
                </button>
            </div>
        </div>
        <div class="card-body">
            {{-- Field tingkat sekolah. Tanggal mulai & jumlah pertemuan ada di
                 sini karena keduanya milik POLA (hari + sekolah), bukan
                 diulang di setiap baris kelas. --}}

            <div class="sched-section">
                <div class="sched-section-title">
                    <i class="bi bi-building"></i> Sekolah &amp; Periode
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-5">
                        <label class="form-label small fw-semibold mb-1">Nama Sekolah <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" data-field="school_id"></select>
                    </div>
                    <div class="col-12 col-sm-7 col-lg-4">
                        <label class="form-label small fw-semibold mb-1">
                            Tanggal Mulai <span class="text-danger">*</span>
                            <span class="text-muted fw-normal">(kolom KET)</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="date" class="form-control form-control-sm" data-field="start_date">
                            <button type="button" class="btn btn-outline-secondary" data-fill-nearest
                                    title="Isi tanggal terdekat yang sesuai hari ini">
                                <i class="bi bi-calendar-check"></i>
                            </button>
                        </div>
                        <div class="form-text d-none text-danger" data-start-warning></div>
                        <div class="form-text" data-start-preview></div>
                    </div>
                    <div class="col-12 col-sm-5 col-lg-3">
                        <label class="form-label small fw-semibold mb-1">Jumlah Pertemuan</label>
                        <input type="number" class="form-control form-control-sm" data-field="meeting_count"
                               min="1" max="60" step="1" value="20">
                        <div class="form-text">Default 20.</div>
                    </div>
                </div>
            </div>

            <div class="sched-section">
                <div class="sched-section-title">
                    <i class="bi bi-bus-front"></i> Keberangkatan
                    <span class="sched-section-hint fw-normal text-muted">opsional</span>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-lg-4">
                        <label class="form-label small fw-semibold mb-1">Lokasi Berangkat</label>
                        <input type="text" class="form-control form-control-sm" data-field="departure_location"
                               maxlength="100" placeholder="cth. BSD">
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label small fw-semibold mb-1">Jam Berangkat</label>
                        <input type="time" class="form-control form-control-sm" data-field="departure_time">
                    </div>
                    <div class="col-6 col-lg-4">
                        <label class="form-label small fw-semibold mb-1">Jam Sampai</label>
                        <input type="time" class="form-control form-control-sm" data-field="arrival_time">
                    </div>
                </div>
            </div>

            <div class="sched-section">
                <div class="sched-section-title">
                    <i class="bi bi-mortarboard"></i> Kelas / Sesi
                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-add-row>
                        <i class="bi bi-plus-lg me-1"></i> Tambah Kelas
                    </button>
                </div>

                <div class="schedule-rows" data-rows-container></div>

                <div class="sched-empty text-center py-3 small text-muted" data-block-empty>
                    Belum ada kelas pada sekolah ini — klik <strong>Tambah Kelas</strong>.
                </div>
            </div>
        </div>
    </div>
</template>

{{-- ============ TEMPLATE BARIS KELAS ============ --}}
<template id="scheduleRowTemplate">
    <div class="sched-row schedule-row">
        <div class="sched-row-head">
            <span class="badge bg-light text-dark border">
                <i class="bi bi-mortarboard me-1"></i>Kelas #<span data-row-number>1</span>
            </span>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn-sm btn-light border" data-duplicate-row title="Duplikat baris">
                    <i class="bi bi-files"></i>
                </button>
                <button type="button" class="btn btn-sm btn-light border text-danger" data-remove-row title="Hapus baris">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>

        <div class="sched-row-body">
            <div class="sched-section-title"><i class="bi bi-book"></i> Kelas &amp; Program</div>
            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-4">
                    <label class="form-label small text-muted mb-1">Kelas</label>
                    <select class="form-select form-select-sm" data-field="class_id"></select>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <label class="form-label small text-muted mb-1">Program</label>
                    <select class="form-select form-select-sm" data-field="program_id"></select>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <label class="form-label small text-muted mb-1">Jml Murid</label>
                    <input type="number" class="form-control form-control-sm" data-field="student_count" min="0" max="255">
                </div>
            </div>

            <div class="sched-section">
                <div class="sched-section-title"><i class="bi bi-person-badge"></i> Coach</div>
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-xl-6">
                        <label class="form-label small text-muted mb-1">Coach Utama</label>
                        <select class="form-select form-select-sm" data-field="coach_id"></select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-6">
                        <label class="form-label small text-muted mb-1">
                            Coach Tambahan <span class="text-muted">(boleh banyak)</span>
                        </label>
                        {{-- size="4" supaya daftar pilihan benar-benar terlihat; dengan
                             size="1" field ini tampak seperti dropdown biasa dan
                             pilihan ganda tidak pernah kelihatan. --}}
                        <select class="form-select form-select-sm" data-field="additional_coaches" multiple size="4"></select>
                        <div class="sched-chips d-flex flex-wrap gap-1 mt-2" data-coach-chips></div>
                        <div class="form-text">
                            Tahan Ctrl / Cmd untuk memilih beberapa coach.
                            Coach yang belum ter-assign permanen ke kelas ini tetap bisa dipilih:
                            ia mendapat akses <strong>sementara</strong> pada sesi ini saja, dan
                            akses itu hilang begitu ia dicopot dari jadwal.
                        </div>
                    </div>
                </div>
            </div>

            <div class="sched-section">
                <div class="sched-section-title"><i class="bi bi-clock"></i> Jam Sesi</div>
                <div class="row g-3">
                    <div class="col-6 col-md-4 col-xl-4">
                        <label class="form-label small text-muted mb-1">Jam Mulai</label>
                        <input type="time" class="form-control form-control-sm" data-field="start_time">
                    </div>
                    <div class="col-6 col-md-4 col-xl-4">
                        <label class="form-label small text-muted mb-1">Jam Selesai</label>
                        <input type="time" class="form-control form-control-sm" data-field="end_time">
                    </div>
                    <div class="col-12 col-md-4 col-xl-4">
                        <label class="form-label small text-muted mb-1 d-block">Minggu Ini?</label>
                        {{-- Status mingguan, BUKAN definisi pola. Mengubahnya tidak
                             mengubah tanggal mulai maupun jumlah pertemuan. --}}
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" data-field="jalan_minggu_ini" value="1" checked>
                            <label class="form-check-label small text-muted">Sesi berjalan</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sched-section">
                <div class="sched-section-title"><i class="bi bi-tools"></i> Peralatan &amp; Catatan</div>
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label small text-muted mb-1">Tools DK</label>
                        <input type="text" class="form-control form-control-sm" data-field="tools_dk" maxlength="255">
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label small text-muted mb-1">Tools RK</label>
                        <input type="text" class="form-control form-control-sm" data-field="tools_rk" maxlength="255">
                    </div>
                    <div class="col-12 col-xl-6">
                        <label class="form-label small text-muted mb-1">KET</label>
                        <input type="text" class="form-control form-control-sm" data-field="keterangan" maxlength="1000"
                               placeholder="cth. Mulai tanggal 3 Agustus 2026">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
/**
 * Form pola jadwal DIGISchool — mengikuti struktur workbook operasional:
 * satu tab per HARI (satu sheet Excel), di dalamnya banyak BLOK SEKOLAH, dan
 * di dalam tiap blok banyak baris kelas/sesi.
 *
 * Dua hal yang membedakan dari form lama:
 *  1. Tidak ada `week_start` global. Tanggal mulai diisi PER BLOK SEKOLAH
 *     (dibaca dari kolom KET), sehingga dua sekolah pada hari yang sama boleh
 *     mulai di tanggal berbeda.
 *  2. `Jalan Minggu Ini?` adalah status mingguan, bukan definisi pola.
 *
 * Payload: days[hari][blocks][blok][ ... ] dengan hari = nomor ISO (1=Senin).
 * Validasi otoritatif tetap di server (ScheduleTemplateService::build).
 */
document.addEventListener('DOMContentLoaded', function () {
    var MASTER = @json($masterData);
    var DAY_NAMES = { 1: 'SENIN', 2: 'SELASA', 3: 'RABU', 4: 'KAMIS', 5: 'JUMAT', 6: 'SABTU', 7: 'MINGGU' };
    var form = document.getElementById('bulkScheduleForm');
    if (!form) return;

    var blockTemplate = document.getElementById('schoolBlockTemplate');
    var rowTemplate = document.getElementById('scheduleRowTemplate');

    var classesBySchool = {};
    var classById = {};
    MASTER.classes.forEach(function (item) {
        classById[item.id] = item;
        (classesBySchool[item.school_id] = classesBySchool[item.school_id] || []).push(item);
    });

    // Pola yang sedang diedit (dari aksi "Duplikat ke Form" pada halaman
    // detail pola). Disiapkan di blok PHP agar parser Blade tidak salah
    // membaca argumen direktif json yang memuat array bertingkat.
    @php
        $currentPattern = null;
        if ($editing) {
            $currentPattern = [
                'day_of_week' => $editing['day_of_week'],
                'blocks' => [[
                    'school_id'          => (int) ($editing['school']->id ?? 0),
                    'start_date'         => $editing['start_date']?->toDateString(),
                    'meeting_count'      => (int) $editing['meeting_count'],
                    'departure_location' => $editing['departure_location'],
                    'departure_time'     => $editing['departure_time']?->format('H:i'),
                    'arrival_time'       => $editing['arrival_time']?->format('H:i'),
                    'rows'               => $editing['rows']->map(fn ($row) => [
                        'class_id'           => (int) $row->class_id,
                        'program_id'         => (int) $row->program_id,
                        'coach_id'           => (int) $row->coach_id,
                        'additional_coaches' => $row->additionalCoaches->pluck('id')->map(fn ($id) => (int) $id)->all(),
                        'start_time'         => $row->start_time?->format('H:i'),
                        'end_time'           => $row->end_time?->format('H:i'),
                        'student_count'      => $row->student_count,
                        'tools_dk'           => $row->tools_dk,
                        'tools_rk'           => $row->tools_rk,
                        'jalan_minggu_ini'   => (bool) $row->jalan_minggu_ini,
                        'keterangan'         => $row->keterangan,
                    ])->values()->all(),
                ]],
            ];
        }
    @endphp
    var CURRENT_PATTERN = @json($currentPattern);

    function option(value, label) {
        var opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        return opt;
    }

    function fillSelect(select, items, placeholder) {
        select.innerHTML = '';
        if (!select.multiple) {
            select.appendChild(option('', placeholder));
        }
        items.forEach(function (item) {
            select.appendChild(option(item.id, item.name));
        });
    }

    // ---------- Tanggal: terdekat, pratinjau, dan peringatan hari ----------
    function isoDayOf(dateString) {
        if (!dateString) return null;
        var date = new Date(dateString + 'T00:00:00');
        if (isNaN(date.getTime())) return null;
        var day = date.getDay();
        return day === 0 ? 7 : day;
    }

    function formatDate(date) {
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function parseDate(dateString) {
        if (!dateString) return null;
        var date = new Date(dateString + 'T00:00:00');
        return isNaN(date.getTime()) ? null : date;
    }

    /**
     * Tanggal terdekat yang jatuh pada hari yang diminta — hanya dipakai saat
     * pengguna menekan tombol "isi tanggal terdekat", tidak pernah otomatis.
     */
    function nearestDate(day) {
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var current = today.getDay() === 0 ? 7 : today.getDay();
        var delta = (day - current + 7) % 7;
        var target = new Date(today.getTime());
        target.setDate(today.getDate() + delta);
        return target;
    }

    function refreshBlockSchedule(block) {
        var day = parseInt(block.dataset.day, 10);
        var startInput = block.querySelector('[data-field="start_date"]');
        var countInput = block.querySelector('[data-field="meeting_count"]');
        var warning = block.querySelector('[data-start-warning]');
        var preview = block.querySelector('[data-start-preview]');

        var start = parseDate(startInput.value);
        var count = parseInt(countInput.value, 10);
        if (!(count >= 1 && count <= 60)) count = 20;

        if (!start) {
            warning.classList.add('d-none');
            preview.textContent = '';
            return;
        }

        // Sistem tidak menebak tanggal: tanggal mulai yang bukan hari terpilih
        // ditolak server, jadi peringatkan lebih awal di sini.
        if (isoDayOf(startInput.value) !== day) {
            warning.textContent = '⚠ ' + formatDate(start) + ' bukan hari ' + (DAY_NAMES[day] || day) + '.';
            warning.classList.remove('d-none');
        } else {
            warning.classList.add('d-none');
        }

        var last = new Date(start.getTime());
        last.setDate(start.getDate() + (count - 1) * 7);
        preview.textContent = 'Pertemuan 1: ' + formatDate(start) + ' → Pertemuan ' + count + ': ' + formatDate(last);
    }

    // ---------- Blok sekolah ----------
    function addBlock(day, focus) {
        var container = document.querySelector('[data-blocks-container="' + day + '"]');
        if (!container) return null;

        var fragment = blockTemplate.content.cloneNode(true);
        var block = fragment.querySelector('.school-block');
        container.appendChild(block);

        // Indeks blok dihitung dari posisi saat ini agar unik per hari.
        block.dataset.day = day;
        block.dataset.index = container.querySelectorAll('.school-block').length - 1;

        var schoolSelect = block.querySelector('[data-field="school_id"]');
        fillSelect(schoolSelect, MASTER.schools, '— Pilih Sekolah —');

        wireBlock(block, schoolSelect);
        addRow(block);
        refreshBlockSchedule(block);
        refreshCounts();

        if (focus) {
            schoolSelect.focus();
        }

        return block;
    }

    function wireBlock(block, schoolSelect) {
        var rowsContainer = block.querySelector('[data-rows-container]');
        var blockTitle = block.querySelector('[data-block-title]');

        // Kelas mengikuti sekolah yang dipilih — satu sumber kebenaran adalah
        // assignment kelas ke sekolah (classes.school_id).
        schoolSelect.addEventListener('change', function () {
            var schoolId = parseInt(schoolSelect.value, 10);
            blockTitle.textContent = schoolSelect.value
                ? schoolSelect.options[schoolSelect.selectedIndex].textContent
                : 'Sekolah';

            rowsContainer.querySelectorAll('.schedule-row').forEach(function (row) {
                refreshRowClassOptions(row, schoolId);
            });
            refreshCounts();
        });

        block.querySelector('[data-field="meeting_count"]').addEventListener('input', function () {
            refreshBlockSchedule(block);
        });
        block.querySelector('[data-field="start_date"]').addEventListener('change', function () {
            refreshBlockSchedule(block);
        });

        block.querySelector('[data-fill-nearest]').addEventListener('click', function () {
            var day = parseInt(block.dataset.day, 10);
            block.querySelector('[data-field="start_date"]').value =
                nearestDate(day).toISOString().slice(0, 10);
            refreshBlockSchedule(block);
        });

        block.querySelector('[data-add-row]').addEventListener('click', function () {
            addRow(block);
            refreshCounts();
        });

        block.querySelector('[data-remove-block]').addEventListener('click', function () {
            if (!window.confirm('Hapus blok sekolah ini beserta seluruh baris kelasnya?')) return;
            block.remove();
            refreshIndices(block.dataset.day);
            refreshCounts();
        });

        block.querySelector('[data-duplicate-block]').addEventListener('click', function () {
            duplicateBlock(block);
        });
    }

    var BLOCK_SHARED_FIELDS = [
        'school_id', 'start_date', 'meeting_count',
        'departure_location', 'departure_time', 'arrival_time'
    ];

    function copyBlockSharedFields(source, target) {
        BLOCK_SHARED_FIELDS.forEach(function (name) {
            var from = source.querySelector('[data-field="' + name + '"]');
            var to = target.querySelector('[data-field="' + name + '"]');
            if (from && to) to.value = from.value;
        });
        target.querySelector('[data-field="school_id"]').dispatchEvent(new Event('change'));
        refreshBlockSchedule(target);
    }

    function duplicateBlock(block) {
        var day = block.dataset.day;
        var container = block.querySelector('[data-rows-container]');
        var rowsSnapshot = [];
        container.querySelectorAll('.schedule-row').forEach(function (row) {
            rowsSnapshot.push(readRow(row));
        });

        var clone = addBlock(day);
        if (!clone) return;

        copyBlockSharedFields(block, clone);

        var cloneRows = clone.querySelector('[data-rows-container]');
        cloneRows.innerHTML = '';
        rowsSnapshot.forEach(function (snapshot) {
            addRow(clone, snapshot);
        });
        clone.querySelector('[data-block-title]').textContent =
            block.querySelector('[data-block-title]').textContent;

        refreshIndices(day);
        refreshCounts();
    }

    // ---------- Baris kelas ----------
    function addRow(block, values) {
        var container = block.querySelector('[data-rows-container]');
        var fragment = rowTemplate.content.cloneNode(true);
        var row = fragment.querySelector('.schedule-row');
        container.appendChild(row);

        var schoolId = parseInt(block.querySelector('[data-field="school_id"]').value, 10) || null;
        refreshRowClassOptions(row, schoolId);

        if (values) {
            writeRow(row, values);
        }

        wireRow(row, block);
        refreshRowNumbers(block);
        refreshBlockEmptyState(block);
        refreshCounts();
    }

    function wireRow(row, block) {
        var classSelect = row.querySelector('[data-field="class_id"]');
        var programSelect = row.querySelector('[data-field="program_id"]');
        var coachSelect = row.querySelector('[data-field="coach_id"]');
        var additionalSelect = row.querySelector('[data-field="additional_coaches"]');
        var countInput = row.querySelector('[data-field="student_count"]');

        // Kelas → program dan coach mengikuti assignment master yang valid.
        classSelect.addEventListener('change', function () {
            var classId = parseInt(classSelect.value, 10);
            var info = classById[classId];

            fillSelect(programSelect, info ? info.programs : [], '— Tanpa Program —');
            fillSelect(coachSelect, info ? info.coaches : [], '— Pilih Coach —');
            // Coach pendamping memakai SELURUH coach dalam scope, bukan hanya
            // yang sudah ter-assign ke kelas ini: coach yang belum punya
            // assignment permanen boleh ditugaskan sementara di level jadwal.
            fillSelect(additionalSelect, MASTER.coaches, '');
            refreshCoachChips(row);

            if (info && info.coaches.length === 1) {
                coachSelect.value = info.coaches[0].id;
            }
            if (!countInput.value) {
                loadRosterCount(classId, countInput);
            }
            refreshCounts();
        });

        additionalSelect.addEventListener('change', function () {
            refreshCoachChips(row);
        });

        row.querySelector('[data-remove-row]').addEventListener('click', function () {
            var wasOnly = block.querySelectorAll('.schedule-row').length === 1;
            if (wasOnly) {
                window.alert('Setiap sekolah minimal memiliki satu baris kelas. Hapus blok sekolah bila tidak diperlukan.');
                return;
            }
            row.remove();
            refreshRowNumbers(block);
            refreshBlockEmptyState(block);
            refreshCounts();
        });

        row.querySelector('[data-duplicate-row]').addEventListener('click', function () {
            addRow(block, readRow(row));
        });
    }

    /**
     * Menampilkan coach tambahan yang sedang terpilih sebagai chip, karena
     * <select multiple> hanya memperlihatkan baris yang tersorot — tanpa ini
     * pilihan ganda mudah terlewat.
     */
    function refreshCoachChips(row) {
        var select = row.querySelector('[data-field="additional_coaches"]');
        var container = row.querySelector('[data-coach-chips]');
        if (!select || !container) return;

        container.innerHTML = '';
        Array.prototype.forEach.call(select.selectedOptions, function (opt) {
            if (!opt.value) return;
            var chip = document.createElement('span');
            chip.className = 'badge text-bg-secondary';
            chip.textContent = opt.textContent;
            container.appendChild(chip);
        });
    }

    function refreshRowClassOptions(row, schoolId) {
        var classSelect = row.querySelector('[data-field="class_id"]');
        var current = classSelect.value;
        var classes = schoolId ? (classesBySchool[schoolId] || []) : [];

        fillSelect(classSelect, classes, schoolId ? '— Pilih Kelas —' : '— Pilih sekolah dulu —');
        classSelect.disabled = !schoolId;

        if (current && classes.some(function (item) { return String(item.id) === String(current); })) {
            classSelect.value = current;
        }
        classSelect.dispatchEvent(new Event('change'));
    }

    function readRow(row) {
        var values = {};
        row.querySelectorAll('[data-field]').forEach(function (field) {
            if (field.multiple) {
                values[field.dataset.field] = Array.prototype.map.call(field.selectedOptions, function (opt) {
                    return opt.value;
                });
            } else if (field.type === 'checkbox') {
                values[field.dataset.field] = field.checked ? '1' : '';
            } else {
                values[field.dataset.field] = field.value;
            }
        });
        return values;
    }

    function writeRow(row, values) {
        var classSelect = row.querySelector('[data-field="class_id"]');

        // Kelas diset lebih dulu dan event change dijalankan, karena handler
        // change membangun ulang dropdown Program, Coach, dan Coach Tambahan.
        // Bila program/coach diisi sebelum itu, nilainya akan tertimpa.
        if (values.class_id !== undefined) {
            classSelect.value = values.class_id;
        }
        classSelect.dispatchEvent(new Event('change'));

        row.querySelectorAll('[data-field]').forEach(function (field) {
            if (field.dataset.field === 'class_id') return;

            var value = values[field.dataset.field];
            if (value === undefined) return;

            if (field.multiple) {
                var wanted = Array.isArray(value) ? value.map(String) : [String(value)];
                Array.prototype.forEach.call(field.options, function (opt) {
                    opt.selected = wanted.indexOf(String(opt.value)) !== -1;
                });
            } else if (field.type === 'checkbox') {
                field.checked = value === '1' || value === 1 || value === true;
            } else {
                field.value = value;
            }
        });

        refreshCoachChips(row);
    }

    // ---------- Jumlah murid dari roster master ----------
    function loadRosterCount(classId, countInput) {
        if (!classId) return;
        fetch('{{ url('admin/schedules/class-students') }}/' + classId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
            .then(function (data) {
                if (!countInput.value && data.count) {
                    countInput.value = data.count;
                }
            })
            .catch(function () { /* jumlah murid tetap bisa diisi manual */ });
    }

    // ---------- Indeks & penomoran ----------
    /**
     * Atribut name dibangun ulang dari posisi DOM sehingga payload selalu
     * days[hari][blocks][blok][rows][baris][field] tanpa celah indeks.
     */
    function refreshIndices(day) {
        var container = document.querySelector('[data-blocks-container="' + day + '"]');
        if (!container) return;

        var blocks = container.querySelectorAll('.school-block');
        blocks.forEach(function (block, blockIndex) {
            block.dataset.index = blockIndex;
            var blockBase = 'days[' + day + '][blocks][' + blockIndex + ']';

            BLOCK_SHARED_FIELDS.forEach(function (name) {
                var field = block.querySelector('[data-field="' + name + '"]');
                if (field) field.name = blockBase + '[' + name + ']';
            });

            var rows = block.querySelectorAll('.schedule-row');
            rows.forEach(function (row, rowIndex) {
                row.querySelectorAll('[data-field]').forEach(function (field) {
                    var suffix = field.multiple ? '[]' : '';
                    var name = blockBase + '[rows][' + rowIndex + '][' + field.dataset.field + ']' + suffix;
                    field.name = name;

                    // Checkbox yang tidak dicentang tidak dikirim browser.
                    // Pasangan hidden bernilai 0 tepat sebelum checkbox membuat
                    // "Minggu Ini?" yang dimatikan tetap terbaca sebagai 0.
                    if (field.type === 'checkbox') {
                        var companion = field.previousElementSibling;
                        if (!companion || companion.dataset.checkboxCompanion === undefined) {
                            companion = document.createElement('input');
                            companion.type = 'hidden';
                            companion.dataset.checkboxCompanion = '1';
                            field.parentNode.insertBefore(companion, field);
                        }
                        companion.name = name;
                        companion.value = '0';
                    }
                });
            });
        });

        refreshDayEmptyState(day);
    }

    function refreshAllIndices() {
        for (var day = 1; day <= 6; day++) {
            refreshIndices(day);
        }
    }

    function refreshRowNumbers(block) {
        block.querySelectorAll('.schedule-row').forEach(function (row, index) {
            row.querySelector('[data-row-number]').textContent = index + 1;
        });
    }

    function refreshBlockEmptyState(block) {
        var hasRows = block.querySelectorAll('.schedule-row').length > 0;
        block.querySelector('[data-block-empty]').classList.toggle('d-none', hasRows);
    }

    function refreshDayEmptyState(day) {
        var container = document.querySelector('[data-blocks-container="' + day + '"]');
        var empty = document.querySelector('[data-empty-state="' + day + '"]');
        if (!container || !empty) return;
        empty.classList.toggle('d-none', container.querySelectorAll('.school-block').length > 0);
    }

    function refreshCounts() {
        var total = 0;

        for (var day = 1; day <= 6; day++) {
            var container = document.querySelector('[data-blocks-container="' + day + '"]');
            var badge = document.getElementById('dayCount' + day);
            if (!container || !badge) continue;

            var filled = 0;
            container.querySelectorAll('.schedule-row').forEach(function (row) {
                if (row.querySelector('[data-field="class_id"]').value) filled++;
            });

            badge.textContent = filled;
            badge.classList.toggle('d-none', filled === 0);
            total += filled;
        }

        // Ringkasan pada bilah aksi yang menempel — supaya jumlah baris siap
        // simpan tetap terlihat walau sedang berada di hari lain.
        var totalLabel = form.querySelector('[data-total-count]');
        if (totalLabel) {
            totalLabel.textContent = total + ' kelas siap disimpan';
        }
    }

    // ---------- Copy pola hari ----------
    /**
     * Menyalin sekolah + baris kelas ke hari lain. Tanggal mulai DIGESER ke
     * hari tujuan (bukan disalin apa adanya) karena tanggal mulai yang tidak
     * jatuh pada hari tujuan akan ditolak saat disimpan. Jumlah pertemuan dan
     * jam ikut tersalin.
     */
    document.getElementById('copyDayPattern').addEventListener('click', function () {
        var active = document.querySelector('[data-day-panel].active');
        if (!active) return;
        var sourceDay = parseInt(active.dataset.dayPanel, 10);
        var sourceLabel = DAY_NAMES[sourceDay] || sourceDay;

        var target = window.prompt(
            'Copy pola ' + sourceLabel + ' ke hari nomor berapa?\n1=Senin 2=Selasa 3=Rabu 4=Kamis 5=Jumat 6=Sabtu',
            ''
        );
        if (!target) return;
        target = parseInt(target, 10);
        if (!(target >= 1 && target <= 6) || target === sourceDay) {
            window.alert('Pilih nomor hari yang berbeda antara 1 sampai 6.');
            return;
        }

        var targetContainer = document.querySelector('[data-blocks-container="' + target + '"]');
        targetContainer.innerHTML = '';

        var sourceBlocks = document.querySelectorAll('[data-blocks-container="' + sourceDay + '"] .school-block');
        sourceBlocks.forEach(function (block) {
            var rowsSnapshot = [];
            block.querySelectorAll('.schedule-row').forEach(function (row) {
                rowsSnapshot.push(readRow(row));
            });

            var clone = addBlock(target);
            if (!clone) return;

            copyBlockSharedFields(block, clone);

            // Geser tanggal mulai ke hari tujuan pada minggu yang sama.
            var startInput = clone.querySelector('[data-field="start_date"]');
            var start = parseDate(startInput.value);
            if (start) {
                start.setDate(start.getDate() + (target - sourceDay));
                startInput.value = start.toISOString().slice(0, 10);
            }
            refreshBlockSchedule(clone);

            var cloneRows = clone.querySelector('[data-rows-container]');
            cloneRows.innerHTML = '';
            rowsSnapshot.forEach(function (snapshot) {
                addRow(clone, snapshot);
            });
        });

        refreshAllIndices();
        refreshCounts();
        window.alert('Pola ' + sourceLabel + ' disalin ke ' + (DAY_NAMES[target] || target) + '. '
            + 'Tanggal mulai digeser ke hari tujuan — periksa jam dan coach.');
    });

    document.getElementById('collapseAllDays').addEventListener('click', function () {
        document.querySelectorAll('[data-blocks-container]').forEach(function (container) {
            container.classList.toggle('d-none');
        });
    });

    document.querySelectorAll('[data-add-school]').forEach(function (button) {
        button.addEventListener('click', function () {
            addBlock(button.dataset.addSchool, true);
        });
    });

    form.addEventListener('submit', refreshAllIndices);
    refreshAllIndices();

    // ---------- Pulihkan isi setelah validasi gagal ----------
    // Penyimpanan bersifat atomik: bila satu blok bermasalah, tidak ada pola
    // yang tersimpan. Isi formulir karena itu dibangun ulang dari old input
    // agar pengguna hanya perlu memperbaiki blok yang salah.
    var OLD_DAYS = @json(old('days', []));

    function restoreDay(day, blocks) {
        var container = document.querySelector('[data-blocks-container="' + day + '"]');
        if (!container) return 0;

        container.innerHTML = '';
        (blocks || []).forEach(function (blockData) {
            var block = addBlock(day);
            if (!block) return;

            BLOCK_SHARED_FIELDS.forEach(function (name) {
                var field = block.querySelector('[data-field="' + name + '"]');
                if (field && blockData[name] !== undefined && blockData[name] !== null) {
                    field.value = blockData[name];
                }
            });
            block.querySelector('[data-field="school_id"]').dispatchEvent(new Event('change'));

            var rowsContainer = block.querySelector('[data-rows-container]');
            var existingRows = rowsContainer.querySelectorAll('.schedule-row');
            var rows = blockData.rows || [];

            rows.forEach(function (rowData, index) {
                if (index < existingRows.length) {
                    writeRow(existingRows[index], rowData);
                    return;
                }
                addRow(block, rowData);
            });

            refreshBlockSchedule(block);
            refreshRowNumbers(block);
            refreshBlockEmptyState(block);
        });

        refreshIndices(day);

        return (blocks || []).length;
    }

    // Old input diprioritaskan; mode edit dipakai saat form baru dibuka.
    var restored = 0;
    Object.keys(OLD_DAYS || {}).forEach(function (dayKey) {
        restored += restoreDay(parseInt(dayKey, 10), OLD_DAYS[dayKey].blocks);
    });

    if (restored === 0 && CURRENT_PATTERN && CURRENT_PATTERN.blocks) {
        restored = restoreDay(CURRENT_PATTERN.day_of_week, CURRENT_PATTERN.blocks);
    }

    // Buka hari yang berisi data agar pesan error / isi pola langsung terlihat.
    if (restored > 0) {
        var firstDay = Object.keys(OLD_DAYS || {}).length > 0
            ? parseInt(Object.keys(OLD_DAYS)[0], 10)
            : (CURRENT_PATTERN ? CURRENT_PATTERN.day_of_week : null);
        var firstTab = firstDay ? document.getElementById('dayTab' + firstDay) : null;
        if (firstTab && typeof bootstrap !== 'undefined') {
            new bootstrap.Tab(firstTab).show();
        }
    }

    // Tab hari bisa berada di luar area terlihat pada layar sempit (strip tab
    // discroll horizontal), jadi tab aktif selalu dibawa ke tengah.
    var activeTab = document.querySelector('#dayTabs .nav-link.active');
    if (activeTab && activeTab.scrollIntoView) {
        activeTab.scrollIntoView({ block: 'nearest', inline: 'center' });
    }

    refreshAllIndices();
    refreshCounts();
});
</script>
@endsection
