@extends('layouts.app')
@section('title', 'Master Program')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canCreateProgram = $currentUser && $authorization->allows($currentUser, 'programs.create');
    $canUpdateProgram = $currentUser && $authorization->allows($currentUser, 'programs.update');
    $canDeleteProgram = $currentUser && $authorization->allows($currentUser, 'programs.delete');
    $hasProgramActions = $canUpdateProgram || $canDeleteProgram || true; // Detail is always available if they can view
    $selectedClassIds = old('class_ids', []);
@endphp

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-book-half text-primary me-2"></i> Master Data Program</h4>
            <p class="text-muted small mb-0">Kelola daftar program pelajaran dan distribusinya ke kelas-kelas.</p>
        </div>
        @if($canCreateProgram)
            <button class="btn btn-primary shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                <i class="bi bi-plus-circle"></i> Tambah Program
            </button>
        @endif
    </div>

    <div class="card shadow-sm border-0 mb-3 bg-light">
        <div class="card-body p-3">
            <form action="{{ route('admin.programs.index') }}" method="GET" class="mb-0">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama program..." value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ route('admin.programs.index') }}" class="btn btn-outline-secondary" title="Reset Search">
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
                        <th class="text-secondary fw-semibold" style="width: 250px;">Program</th>
                        <th class="text-secondary fw-semibold">Kelas / Sekolah</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 150px;">Status</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($programs as $program)
                    <tr>
                        <td class="text-center text-muted small">{{ ($programs->currentPage() - 1) * $programs->perPage() + $loop->iteration }}</td>
                        <td>
                            <div class="fw-bold text-dark mb-1">{{ $program->name }}</div>
                            @if($program->code)
                                <span class="badge bg-light text-secondary border mb-1 font-monospace">{{ $program->code }}</span>
                            @endif
                            @if($program->description)
                                <div class="small text-muted text-truncate" style="max-width: 200px;" title="{{ $program->description }}">{{ $program->description }}</div>
                            @endif
                        </td>
                        <td>
                            @forelse($program->programClasses as $programClass)
                                <span class="badge bg-light text-dark border border-secondary-subtle mb-1 me-1">
                                    <i class="bi bi-building text-muted me-1"></i> {{ $programClass->schoolClass->school->name }} - {{ $programClass->schoolClass->name }}
                                </span>
                            @empty
                                <span class="text-muted small fst-italic">Belum dikaitkan ke kelas.</span>
                            @endforelse
                        </td>
                        <td class="text-center">
                            @if($program->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Aktif
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1">
                                    <i class="bi bi-dash-circle-fill me-1"></i> Tidak Aktif
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="{{ route('admin.programs.show', $program) }}" class="btn btn-sm btn-outline-info" title="Detail">
                                    Detail
                                </a>
                                @if($canUpdateProgram)
                                    <button class="btn btn-sm btn-light border text-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editProgramModal{{ $program->id }}" title="Edit Program">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                @endif
                                @if($canDeleteProgram)
                                    <button type="button" onclick="confirmAction('{{ route('admin.programs.destroy', $program) }}', 'Apakah Anda yakin ingin menghapus program ini?')" class="btn btn-sm btn-light border text-danger rounded-pill px-3" title="Hapus Program">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    @if($canUpdateProgram)
                    <div class="modal fade" id="editProgramModal{{ $program->id }}" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <form method="POST" action="{{ route('admin.programs.update', $program) }}">
                                    @csrf @method('PUT')
                                    <div class="modal-header bg-primary text-white border-bottom-0">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i> Edit Program</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-8">
                                                <label class="form-label text-muted small fw-semibold">Nama Program <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="form-control bg-light" value="{{ $program->name }}" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-muted small fw-semibold">Kode</label>
                                                <input type="text" name="code" class="form-control bg-light text-uppercase font-monospace" value="{{ $program->code }}">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Deskripsi</label>
                                            <textarea name="description" class="form-control bg-light" rows="3">{{ $program->description }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Status <span class="text-danger">*</span></label>
                                            <select name="status" class="form-select bg-light" required>
                                                <option value="active" @selected($program->status === 'active')>Aktif</option>
                                                <option value="inactive" @selected($program->status === 'inactive')>Tidak Aktif</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Program Kelas <span class="text-danger">*</span></label>
                                            @php
                                                $progClasses = $program->classes->pluck('id')->toArray();
                                            @endphp
                                            <select name="class_ids[]" class="form-select bg-light" multiple size="8" required>
                                                @foreach($classes->groupBy('school.name') as $schoolName => $schoolClasses)
                                                    <optgroup label="{{ $schoolName }}">
                                                        @foreach($schoolClasses as $class)
                                                            <option value="{{ $class->id }}" @selected(in_array($class->id, $progClasses))>
                                                                {{ $class->name }}
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
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
                        <td colspan="5" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64" class="opacity-50 mb-3">
                            <h6 class="text-muted mb-0">Belum ada program.</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($programs->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $programs->links() }}
            </div>
        @endif
    </div>
</div>

@if($canCreateProgram)
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('admin.programs.store') }}">
                @csrf
                <div class="modal-header bg-primary text-white border-bottom-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> Tambah Program Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label text-muted small fw-semibold">Nama Program <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control bg-light" value="{{ old('name') }}" required placeholder="Contoh: English for Kids">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small fw-semibold">Kode</label>
                            <input type="text" name="code" class="form-control bg-light text-uppercase font-monospace" value="{{ old('code') }}" placeholder="ENG-KIDS">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Deskripsi</label>
                        <textarea name="description" class="form-control bg-light" rows="3" placeholder="Deskripsi singkat mengenai program ini...">{{ old('description') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select bg-light" required>
                            <option value="active" @selected(old('status', 'active') === 'active')>Aktif</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Tidak Aktif</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Program Kelas <span class="text-danger">*</span></label>
                        <select name="class_ids[]" class="form-select bg-light" multiple size="8" required>
                            @foreach($classes->groupBy('school.name') as $schoolName => $schoolClasses)
                                <optgroup label="{{ $schoolName }}">
                                    @foreach($schoolClasses as $class)
                                        <option value="{{ $class->id }}" @selected(in_array($class->id, $selectedClassIds))>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <div class="form-text small mt-2"><i class="bi bi-info-circle"></i> Pilih satu atau lebih kelas. Gunakan Ctrl/Cmd+Klik untuk memilih banyak. Program yang sama dapat digunakan lintas sekolah.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-medium">Simpan Program</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
