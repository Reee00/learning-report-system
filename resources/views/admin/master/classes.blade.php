@extends('layouts.app')
@section('title', 'Master Program Kelas')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canCreateClass = $currentUser && $authorization->allows($currentUser, 'program_classes.create');
    $canUpdateClass = $currentUser && $authorization->allows($currentUser, 'program_classes.update');
    $canDeleteClass = $currentUser && $authorization->allows($currentUser, 'program_classes.delete');
    $canViewStudents = $currentUser && $authorization->allows($currentUser, 'students.view');
    $hasClassActions = $canUpdateClass || $canDeleteClass || $canViewStudents;
@endphp

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
                        <x-breadcrumb :items="[
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Program Kelas'],
            ]" />
<h1 class="page-title">Master Program Kelas</h1>
            <p class="text-muted small mb-0">Kelola daftar program kelas dan siswa per sekolah.</p>
        </div>
        @if($canCreateClass)
            <button class="btn btn-primary shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="bi bi-plus-lg"></i> Tambah Kelas
            </button>
        @endif
    </div>

    <div class="card shadow-sm border-0 mb-3 bg-light">
        <div class="card-body p-3">
            <form action="{{ route('admin.classes.index') }}" method="GET" class="mb-0">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama kelas..." value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ route('admin.classes.index') }}" class="btn btn-outline-secondary" title="Reset Search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                    <button type="submit" class="btn btn-primary px-4 fw-medium">Search</button>
                </div>
            </form>
        </div>
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

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold text-center" style="width: 50px;">#</th>
                        <th class="text-secondary fw-semibold">Nama Program Kelas</th>
                        <th class="text-secondary fw-semibold">Sekolah</th>
                        <th class="text-secondary fw-semibold">Program</th>
                        <th class="text-secondary fw-semibold">Digunakan di</th>
                        @if($hasClassActions)
                            <th class="text-center text-secondary fw-semibold" style="width: 200px;">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse($classes as $class)
                    <tr>
                        <td class="text-center text-muted small">{{ ($classes->currentPage() - 1) * $classes->perPage() + $loop->iteration }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $class->name }}</div>
                        </td>
                        <td>
                            <a href="{{ route('admin.schools.show', $class->school) }}"
                               class="badge bg-light text-dark border border-secondary-subtle px-2 py-1 text-decoration-none"
                               title="Buka workspace sekolah">
                                <i class="bi bi-building text-muted me-1"></i> {{ $class->school->name }}
                            </a>
                        </td>
                        <td>
                            @forelse($class->programs->sortBy('name') as $program)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1 mb-1">{{ $program->name }}</span>
                            @empty
                                <span class="small text-muted fst-italic">Belum ada</span>
                            @endforelse
                        </td>
                        <td class="small text-muted">
                            <span class="badge bg-light text-dark border me-1 mb-1">
                                <i class="bi bi-people me-1"></i>{{ $class->students_count }} murid
                            </span>
                            <span class="badge bg-light text-dark border me-1 mb-1">
                                <i class="bi bi-calendar3 me-1"></i>{{ $class->teaching_schedules_count }} jadwal
                            </span>
                            <span class="badge bg-light text-dark border mb-1">
                                <i class="bi bi-file-earmark-text me-1"></i>{{ $class->reports_count }} laporan
                            </span>
                        </td>
                        @if($hasClassActions)
                            <td>
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    @if($canViewStudents)
                                        <a href="{{ route('students.show', $class) }}" class="btn btn-sm btn-light border text-info rounded-pill px-3" title="Kelola Siswa">
                                            <i class="bi bi-people-fill me-1"></i> Siswa
                                        </a>
                                    @endif
                                    @if($canUpdateClass)
                                        <button class="btn btn-sm btn-light border text-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editClassModal{{ $class->id }}" title="Edit Kelas">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                    @endif
                                    @if($canDeleteClass)
                                        <button type="button" onclick="confirmAction('{{ route('admin.classes.destroy', $class) }}', 'Hapus kelas {{ $class->name }}? Kelas yang masih memiliki murid, laporan, atau jadwal akan ditolak oleh sistem.')" class="btn btn-sm btn-light border text-danger rounded-pill px-3" title="Hapus Kelas">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>

                    @if($canUpdateClass)
                    <div class="modal fade" id="editClassModal{{ $class->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <form method="POST" action="{{ route('admin.classes.update', $class) }}">
                                    @csrf
                                    <input type="hidden" name="_modal" value="edit">
                                    <input type="hidden" name="_modal_id" value="{{ $class->id }}">
                                    @method('PUT')
                                    <div class="modal-header bg-primary text-white border-bottom-0">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i> Edit Program Kelas</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Sekolah <span class="text-danger">*</span></label>
                                            <select name="school_id" class="form-select bg-light" required>
                                                @foreach($schools as $school)
                                                    <option value="{{ $school->id }}" @selected($class->school_id == $school->id)>
                                                        {{ $school->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Nama Program Kelas <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control bg-light" value="{{ $class->name }}" required placeholder="Contoh: Grade 5A, Kelas 3B">
                                        </div>
                                        {{-- Target pertemuan kelas ini; kosong = ikut pola jadwal. --}}
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Target Pertemuan</label>
                                            <input type="number" name="target_meetings" class="form-control bg-light"
                                                   min="1" max="60" value="{{ old('target_meetings', $class->target_meetings) }}"
                                                   placeholder="Contoh: 10">
                                            <div class="form-text">Jumlah pertemuan yang direncanakan. Kosongkan bila belum ditetapkan.</div>
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
                @empty
                    <tr>
                        <td colspan="{{ $hasClassActions ? 6 : 5 }}" class="text-center py-5">
                            <i class="bi bi-journal-bookmark text-muted opacity-50 mb-3 d-block lh-1" style="font-size: 4rem;" aria-hidden="true"></i>
                            <h6 class="text-muted mb-0">Belum ada program kelas.</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($classes->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $classes->links() }}
            </div>
        @endif
    </div>
</div>

@if($canCreateClass)
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('admin.classes.store') }}">
                @csrf
                <input type="hidden" name="_modal" value="add">
                <div class="modal-header bg-primary text-white border-bottom-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> Tambah Program Kelas</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Sekolah <span class="text-danger">*</span></label>
                        <select name="school_id" class="form-select bg-light" required>
                            <option value="">Pilih Sekolah</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>
                                    {{ $school->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Nama Program Kelas <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-light" value="{{ old('name') }}" required placeholder="Contoh: Grade 5A, Kelas 3B">
                    </div>
                    {{-- Target pertemuan = jumlah sesi yang harus dimiliki kelas ini.
                         Dipakai untuk menampilkan progres "3/10 pertemuan". Boleh
                         dikosongkan: angkanya lalu mengikuti pola jadwal kelas. --}}
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Target Pertemuan</label>
                        <input type="number" name="target_meetings" class="form-control bg-light"
                               min="1" max="60" value="{{ old('target_meetings') }}" placeholder="Contoh: 10">
                        <div class="form-text">Jumlah pertemuan yang direncanakan. Kosongkan bila belum ditetapkan.</div>
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
@include('partials.modal-reopen', ['modalId' => 'editClassModal'.old('_modal_id'), 'when' => old('_modal') === 'edit'])
@include('partials.modal-reopen', ['modalId' => 'addClassModal', 'when' => old('_modal') === 'add'])
@endsection
