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
            <h4 class="mb-1 fw-bold"><i class="bi bi-journal-bookmark text-primary me-2"></i> Master Program Kelas</h4>
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

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
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
                            <span class="badge bg-light text-dark border border-secondary-subtle px-2 py-1">
                                <i class="bi bi-building text-muted me-1"></i> {{ $class->school->name }}
                            </span>
                        </td>
                        @if($hasClassActions)
                            <td>
                                <div class="d-flex justify-content-center gap-2">
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
                                        <button type="button" onclick="confirmAction('{{ route('admin.classes.destroy', $class) }}', 'Apakah Anda yakin ingin menghapus kelas ini? Semua data siswa yang ada di kelas ini akan ikut terhapus.')" class="btn btn-sm btn-light border text-danger rounded-pill px-3" title="Hapus Kelas">
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
                                    @csrf @method('PUT')
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
                        <td colspan="{{ $hasClassActions ? 4 : 3 }}" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64" class="opacity-50 mb-3">
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
@endsection
