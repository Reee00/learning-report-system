@extends('layouts.app')
@section('title', 'Workspace Sekolah: ' . $school->name)

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canUpdateSchool = $currentUser && $authorization->allows($currentUser, 'schools.update');
    $canDeleteSchool = $currentUser && $authorization->allows($currentUser, 'schools.delete');
    $canCreateClass = $currentUser && $authorization->allows($currentUser, 'program_classes.create');
    $canUpdateClass = $currentUser && $authorization->allows($currentUser, 'program_classes.update');
    $canDeleteClass = $currentUser && $authorization->allows($currentUser, 'program_classes.delete');

    // Ringkasan program dari kelas-kelas sekolah ini (turunan, bukan data baru).
    $usedPrograms = $classes->flatMap->programs->unique('id')->sortBy('name')->values();
    $errorBag = session('errors');
@endphp

<div class="container py-4">
    {{-- ============ HEADER ============ --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="flex-grow-1" style="min-width: 0;">
            <a href="{{ route('admin.schools.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left"></i> Kembali ke Master Sekolah
            </a>
                        <x-breadcrumb :items="[
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Sekolah', 'url' => route('admin.schools.index')],
                ['label' => $school->name],
            ]" />
<h1 class="page-title">{{ $school->name }}</h1>
            <p class="text-muted small mb-0">
                Workspace sekolah — kelola kelas dan program di satu halaman.
            </p>
        </div>
        @if($canCreateClass)
            <button class="btn btn-primary shadow-sm d-flex align-items-center gap-2"
                    data-bs-toggle="modal" data-bs-target="#assignClassModal">
                <i class="bi bi-plus-circle"></i> Tambah / Assign Kelas
            </button>
        @endif
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <h6 class="mb-0 fw-bold">Terjadi Kesalahan</h6>
            </div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- ============ 1. INFORMASI SEKOLAH ============ --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-info-circle text-primary me-2"></i>Informasi Sekolah
                    </h6>
                    @if($canUpdateSchool)
                        <button class="btn btn-sm btn-light border text-secondary rounded-pill px-3"
                                data-bs-toggle="modal" data-bs-target="#editSchoolModal" title="Edit sekolah">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-4 text-muted fw-normal">Nama</dt>
                        <dd class="col-8 fw-semibold text-break">{{ $school->name }}</dd>

                        <dt class="col-4 text-muted fw-normal">Alamat</dt>
                        <dd class="col-8 text-break">{{ $school->address ?: '—' }}</dd>

                        <dt class="col-4 text-muted fw-normal">PIC</dt>
                        <dd class="col-8 text-break">{{ $school->pic_name ?: '—' }}</dd>

                        <dt class="col-4 text-muted fw-normal">Kelas</dt>
                        <dd class="col-8">
                            <span class="badge bg-light text-dark border">{{ $classes->count() }} kelas</span>
                        </dd>

                        <dt class="col-4 text-muted fw-normal">Program</dt>
                        <dd class="col-8">
                            <span class="badge bg-light text-dark border">{{ $usedPrograms->count() }} program</span>
                        </dd>
                    </dl>

                    <hr>

                    <div class="small fw-semibold text-muted mb-2">Program yang digunakan</div>
                    @forelse($usedPrograms as $program)
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1 mb-1">
                            {{ $program->name }}
                        </span>
                    @empty
                        <div class="small text-muted fst-italic">Belum ada program terpasang di kelas mana pun.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ============ 2. KELAS DI SEKOLAH INI ============ --}}
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-journal-bookmark text-primary me-2"></i>Kelas di Sekolah Ini
                        <span class="badge bg-primary rounded-pill ms-2">{{ $classes->count() }}</span>
                    </h6>
                    @if($canCreateClass)
                        <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1"
                                data-bs-toggle="modal" data-bs-target="#assignClassModal">
                            <i class="bi bi-plus-lg"></i> Tambah Kelas
                        </button>
                    @endif
                </div>
                <div class="card-body">
                    @forelse($classes as $class)
                        <div class="border border-light-subtle rounded-3 p-3 mb-3 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div class="flex-grow-1" style="min-width: 0;">
                                    <div class="fw-bold text-dark text-break">{{ $class->name }}</div>
                                    <div class="small text-muted mt-1">
                                        <span class="me-2">
                                            <i class="bi bi-people me-1"></i>{{ $class->students_count }} murid
                                        </span>
                                        @if($class->coachAssignments->isNotEmpty())
                                            <span>
                                                <i class="bi bi-person-badge me-1"></i>
                                                {{ $class->coachAssignments->pluck('coach.name')->filter()->implode(', ') }}
                                            </span>
                                        @else
                                            <span class="fst-italic">Belum ada coach</span>
                                        @endif
                                    </div>
                                </div>
                                @if($canUpdateClass || $canDeleteClass)
                                    <div class="d-flex gap-2 flex-shrink-0">
                                        @if($canUpdateClass)
                                            <button class="btn btn-sm btn-light border text-secondary rounded-pill px-3"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editClassModal{{ $class->id }}"
                                                    title="Edit kelas &amp; program">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                        @endif
                                        @if($canDeleteClass)
                                            <form method="POST"
                                                  id="deleteClassForm{{ $class->id }}"
                                                  action="{{ route('admin.schools.classes.destroy', [$school, $class]) }}">
                                                @csrf @method('DELETE')
                                                <button type="button"
                                                        onclick="confirmSubmitForm('deleteClassForm{{ $class->id }}', 'Hapus kelas {{ $class->name }} dari {{ $school->name }}?', 'btn-danger', 'Ya, Hapus')"
                                                        class="btn btn-sm btn-light border text-danger rounded-pill px-3"
                                                        title="Hapus kelas"
                                                        aria-label="Hapus kelas {{ $class->name }}">
                                                    <i class="bi bi-trash-fill" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Program terpasang pada kelas ini --}}
                            <div class="mt-2 d-flex align-items-center flex-wrap gap-1">
                                <span class="small text-muted me-1">Program:</span>
                                @forelse($class->programs->sortBy('name') as $program)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        {{ $program->name }}
                                    </span>
                                @empty
                                    <span class="small text-muted fst-italic">Belum ada program</span>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted opacity-50 mb-3 d-block"></i>
                            <h6 class="text-muted mb-1">Belum ada kelas di sekolah ini.</h6>
                            <p class="small text-muted mb-3">
                                Tambahkan kelas baru atau assign kelas yang sudah ada di master.
                            </p>
                            @if($canCreateClass)
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignClassModal">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah / Assign Kelas
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============ MODAL: EDIT SEKOLAH ============ --}}
@if($canUpdateSchool)
<div class="modal fade" id="editSchoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('admin.schools.update', $school) }}">
                @csrf @method('PUT')
                <div class="modal-header bg-light border-bottom-0">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-pencil-square text-primary me-2"></i> Edit Sekolah
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Nama Sekolah <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control bg-light" value="{{ $school->name }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Alamat</label>
                            <textarea name="address" class="form-control bg-light" rows="2">{{ $school->address }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Nama PIC</label>
                            <input type="text" name="pic_name" class="form-control bg-light" value="{{ $school->pic_name }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-medium">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ============ MODAL: TAMBAH / ASSIGN KELAS ============ --}}
@if($canCreateClass)
<div class="modal fade" id="assignClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('admin.schools.classes.store', $school) }}" id="assignClassForm">
                @csrf
                <div class="modal-header bg-primary text-white border-bottom-0">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-plus-circle me-2"></i> Tambah / Assign Kelas
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted">
                        Kelas akan langsung terlihat di halaman <strong>{{ $school->name }}</strong> setelah disimpan.
                    </p>

                    <div class="row g-3">
                        {{-- Mode pemilihan --}}
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Sumber Kelas <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group" aria-label="Sumber kelas">
                                <input type="radio" class="btn-check" name="mode" id="modeNew" value="new"
                                       {{ old('mode', 'new') === 'new' ? 'checked' : '' }}>
                                <label class="btn btn-outline-primary" for="modeNew">
                                    <i class="bi bi-plus-lg me-1"></i> Kelas Baru
                                </label>

                                <input type="radio" class="btn-check" name="mode" id="modeExisting" value="existing"
                                       {{ old('mode') === 'existing' ? 'checked' : '' }}
                                       {{ $movableClasses->isEmpty() ? 'disabled' : '' }}>
                                <label class="btn btn-outline-primary" for="modeExisting">
                                    <i class="bi bi-arrow-left-right me-1"></i> Kelas yang Sudah Ada
                                </label>
                            </div>
                            @if($movableClasses->isEmpty())
                                <div class="form-text">
                                    Belum ada kelas master kosong dari sekolah lain yang bisa dipindahkan.
                                </div>
                            @endif
                        </div>

                        {{-- Nama kelas baru --}}
                        <div class="col-md-6" data-mode-panel="new">
                            <label class="form-label text-muted small fw-semibold">Nama Kelas Baru <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="newClassName" class="form-control bg-light"
                                   maxlength="100" placeholder="cth. Grade 5A" value="{{ old('name') }}">
                            <div class="form-text">Nama kelas harus unik di sekolah ini.</div>
                        </div>

                        {{-- Pilih kelas existing --}}
                        <div class="col-md-6 d-none" data-mode-panel="existing">
                            <label class="form-label text-muted small fw-semibold">Kelas Master <span class="text-danger">*</span></label>
                            <select name="class_id" id="existingClassId" class="form-select bg-light">
                                <option value="">— Pilih Kelas —</option>
                                @foreach($movableClasses as $movable)
                                    <option value="{{ $movable->id }}"
                                        {{ (string) old('class_id') === (string) $movable->id ? 'selected' : '' }}>
                                        {{ $movable->name }} — dari {{ $movable->school->name ?? 'sekolah lain' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Kelas akan <strong>dipindahkan</strong> ke {{ $school->name }}.
                                Hanya kelas tanpa murid, laporan, dan jadwal yang bisa dipindahkan.
                            </div>
                        </div>

                        {{-- Program --}}
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Program (dari Master Program)</label>
                            <select name="program_ids[]" class="form-select bg-light" multiple size="5">
                                @foreach($programs as $program)
                                    <option value="{{ $program->id }}"
                                        {{ in_array($program->id, array_map('intval', old('program_ids', [])), true) ? 'selected' : '' }}>
                                        {{ $program->name }}{{ $program->code ? ' (' . $program->code . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Tahan Ctrl / Cmd untuk memilih beberapa program. Program yang sudah terpasang tidak akan terduplikasi.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-medium">Simpan Kelas</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- ============ MODAL: EDIT KELAS (satu per kelas) ============ --}}
@if($canUpdateClass)
    @foreach($classes as $class)
        <div class="modal fade" id="editClassModal{{ $class->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <form method="POST" action="{{ route('admin.schools.classes.update', [$school, $class]) }}">
                        @csrf @method('PUT')
                        <div class="modal-header bg-light border-bottom-0">
                            <h5 class="modal-title fw-bold text-dark">
                                <i class="bi bi-pencil-square text-primary me-2"></i> Edit Kelas
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-semibold">Nama Kelas <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control bg-light"
                                       value="{{ $class->name }}" maxlength="100" required>
                            </div>
                            <div class="mb-0">
                                <label class="form-label text-muted small fw-semibold">Program</label>
                                <select name="program_ids[]" class="form-select bg-light" multiple size="5">
                                    @foreach($programs as $program)
                                        <option value="{{ $program->id }}"
                                            {{ $class->programs->contains('id', $program->id) ? 'selected' : '' }}>
                                            {{ $program->name }}{{ $program->code ? ' (' . $program->code . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    Hapus pilihan untuk melepas program dari kelas ini. Tahan Ctrl / Cmd untuk memilih beberapa.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top-0">
                            <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary px-4 fw-medium">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif

@php
    // Dihitung di PHP lalu dikirim sebagai boolean ke JS agar blok script
    // tidak berisi directive Blade yang menyulitkan pengecekan sintaks.
    $reopenAssignModal = $errorBag
        && ($errorBag->has('name') || $errorBag->has('class_id'));
@endphp
<script>
    var REOPEN_ASSIGN_MODAL = @json((bool) $reopenAssignModal);

    // Panel formulir assign kelas mengikuti mode yang dipilih; hanya field
    // milik mode aktif yang di-submit (field mode lain di-disable).
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('assignClassForm');
        if (!form) return;

        var panels = {
            new: form.querySelectorAll('[data-mode-panel="new"]'),
            existing: form.querySelectorAll('[data-mode-panel="existing"]')
        };
        var nameInput = document.getElementById('newClassName');
        var classSelect = document.getElementById('existingClassId');

        function applyMode() {
            var mode = form.querySelector('input[name="mode"]:checked');
            mode = mode ? mode.value : 'new';

            Object.keys(panels).forEach(function (key) {
                var active = key === mode;
                panels[key].forEach(function (panel) {
                    panel.classList.toggle('d-none', !active);
                });
            });

            if (nameInput) {
                nameInput.required = mode === 'new';
                nameInput.disabled = mode !== 'new';
            }
            if (classSelect) {
                classSelect.required = mode === 'existing';
                classSelect.disabled = mode !== 'existing';
            }
        }

        form.querySelectorAll('input[name="mode"]').forEach(function (radio) {
            radio.addEventListener('change', applyMode);
        });

        applyMode();

        // Bila validasi gagal, buka kembali modal assign agar pesan error
        // terlihat tanpa pengguna harus mencari tombolnya lagi.
        if (REOPEN_ASSIGN_MODAL) {
            var assignModal = document.getElementById('assignClassModal');
            if (assignModal && window.bootstrap) {
                new bootstrap.Modal(assignModal).show();
            }
        }
    });
</script>
@endsection
