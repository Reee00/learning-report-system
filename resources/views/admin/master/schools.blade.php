@extends('layouts.app')
@section('title', 'Master Sekolah')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canCreateSchool = $currentUser && $authorization->allows($currentUser, 'schools.create');
    $canUpdateSchool = $currentUser && $authorization->allows($currentUser, 'schools.update');
    $canDeleteSchool = $currentUser && $authorization->allows($currentUser, 'schools.delete');
    $hasSchoolActions = $canUpdateSchool || $canDeleteSchool;
@endphp

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-building text-primary me-2"></i> Master Data Sekolah</h4>
            <p class="text-muted small mb-0">Kelola daftar sekolah mitra dan informasi penanggung jawab.</p>
        </div>
        @if($canCreateSchool)
            <button class="btn btn-primary shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addSchoolModal">
                <i class="bi bi-plus-circle"></i> Tambah Sekolah
            </button>
        @endif
    </div>

    <div class="card shadow-sm border-0 mb-3 bg-light">
        <div class="card-body p-3">
            <form action="{{ route('admin.schools.index') }}" method="GET" class="mb-0">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama sekolah..." value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ route('admin.schools.index') }}" class="btn btn-outline-secondary" title="Reset Search">
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

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold text-center" style="width: 50px;">#</th>
                        <th class="text-secondary fw-semibold">Nama Sekolah</th>
                        <th class="text-secondary fw-semibold">PIC</th>
                        <th class="text-center text-secondary fw-semibold">Jumlah Kelas</th>
                        @if($hasSchoolActions)
                            <th class="text-center text-secondary fw-semibold" style="width: 150px;">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse($schools as $school)
                    <tr>
                        <td class="text-center text-muted small">{{ $loop->iteration }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="bi bi-building fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $school->name }}</div>
                                    <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i>{{ $school->address ?? 'Alamat belum diatur' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($school->pic_name)
                                <div class="d-flex align-items-center">
                                    <div class="bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center me-2 border border-secondary-subtle" style="width: 28px; height: 28px; font-size: 0.8rem;">
                                        {{ substr($school->pic_name, 0, 1) }}
                                    </div>
                                    <span class="fw-medium text-dark">{{ $school->pic_name }}</span>
                                </div>
                            @else
                                <span class="text-muted small fst-italic">Belum ada PIC</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border border-secondary-subtle px-3 py-1 fs-6">
                                {{ $school->classes_count }} <span class="fw-normal text-muted small ms-1">Kelas</span>
                            </span>
                        </td>
                        @if($hasSchoolActions)
                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                        <a href="{{ route('admin.schools.show', $school) }}" class="btn btn-sm btn-outline-info">
                                        Detail
                                    </a>
                                @if($canUpdateSchool)
                                        <button class="btn btn-sm btn-light border text-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editSchoolModal{{ $school->id }}" title="Edit Sekolah">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                    @endif
                                    @if($canDeleteSchool)
                                        <button type="button" onclick="confirmAction('{{ route('admin.schools.destroy', $school) }}', 'Apakah Anda yakin ingin menghapus sekolah ini? Semua data terkait (kelas, dsb) mungkin akan ikut terpengaruh.')" class="btn btn-sm btn-light border text-danger rounded-pill px-3" title="Hapus Sekolah">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>

                    @if($canUpdateSchool)
                    {{-- Modal Edit --}}
                    <div class="modal fade" id="editSchoolModal{{ $school->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <form method="POST" action="{{ route('admin.schools.update', $school) }}">
                                    @csrf @method('PUT')
                                    <div class="modal-header bg-light border-bottom-0">
                                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Sekolah</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Nama Sekolah <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control bg-light" value="{{ $school->name }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Alamat</label>
                                            <textarea name="address" class="form-control bg-light" rows="2">{{ $school->address }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Nama PIC</label>
                                            <input type="text" name="pic_name" class="form-control bg-light" value="{{ $school->pic_name }}">
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
                        <td colspan="{{ $hasSchoolActions ? 5 : 4 }}" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64" class="opacity-50 mb-3">
                            <h6 class="text-muted mb-0">Belum ada sekolah terdaftar.</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($schools->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $schools->links() }}
            </div>
        @endif
    </div>
</div>

@if($canCreateSchool)
{{-- Modal Tambah Sekolah --}}
<div class="modal fade" id="addSchoolModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('admin.schools.store') }}">
                @csrf
                <div class="modal-header bg-primary text-white border-bottom-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> Tambah Sekolah Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Nama Sekolah <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-light" required placeholder="Contoh: SD Negeri 1 Jakarta">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Alamat</label>
                        <textarea name="address" class="form-control bg-light" rows="2" placeholder="Alamat lengkap sekolah (opsional)"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Nama PIC (Opsional)</label>
                        <input type="text" name="pic_name" class="form-control bg-light"
                               placeholder="Nama penanggung jawab di sekolah">
                    </div>
                    
                    <hr>
                    <h6 class="fw-bold mb-3">Setup Awal (Opsional)</h6>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Daftar Kelas Baru</label>
                        <textarea name="class_names" class="form-control bg-light" rows="3"
                                  placeholder="Pisahkan dengan koma atau baris baru. Contoh: Grade 1, Grade 2"></textarea>
                        <div class="form-text small">Kelas-kelas ini akan otomatis dibuat dan dihubungkan ke sekolah ini.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Hubungkan ke Program</label>
                        <select name="program_ids[]" class="form-select bg-light" multiple size="4">
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}">{{ $program->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text small">Program yang dipilih akan otomatis dihubungkan ke semua kelas baru di atas. (Gunakan Ctrl/Cmd+Klik untuk memilih banyak)</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-medium">Simpan Sekolah</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
