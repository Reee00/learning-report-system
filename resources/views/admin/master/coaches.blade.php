@extends('layouts.app')
@section('title', 'Master Coach')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canCreateCoach = $currentUser && $authorization->allows($currentUser, 'coaches.create');
    $canUpdateCoach = $currentUser && $authorization->allows($currentUser, 'coaches.update');
@endphp

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-person-video3 text-primary me-2"></i> Master Data Coach</h4>
            <p class="text-muted small mb-0">Kelola daftar coach dan penugasan kelas mereka.</p>
        </div>
        @if($canCreateCoach)
            <button class="btn btn-primary shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addCoachModal">
                <i class="bi bi-person-plus-fill"></i> Tambah Coach
            </button>
        @endif
    </div>

    <div class="card shadow-sm border-0 mb-3 bg-light">
        <div class="card-body p-3">
            <form action="{{ route('admin.coaches.index') }}" method="GET" class="mb-0">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama coach atau email..." value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ route('admin.coaches.index') }}" class="btn btn-outline-secondary" title="Reset Search">
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
                        <th class="text-secondary fw-semibold">Coach</th>
                        <th class="text-secondary fw-semibold">Kelas yang Di-assign</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 250px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($coaches as $coach)
                    <tr>
                        <td class="text-center text-muted small">{{ ($coaches->currentPage() - 1) * $coaches->perPage() + $loop->iteration }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                    {{ substr($coach->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $coach->name }}</div>
                                    <div class="small text-muted">{{ $coach->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($coach->coachClasses->isEmpty())
                                <span class="text-muted small fst-italic">Belum ada assignment</span>
                            @else
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($coach->coachClasses as $assignment)
                                        <span class="badge bg-light text-dark border border-secondary-subtle">
                                            <i class="bi bi-journal-text text-muted me-1"></i> {{ $assignment->schoolClass->school->name }} - {{ $assignment->schoolClass->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="{{ route('admin.coaches.show', $coach) }}" class="btn btn-sm btn-light border text-primary rounded-pill px-3" title="Kelola Assignment">
                                    <i class="bi bi-clipboard2-check me-1"></i> Assignment
                                </a>
                                @if($canUpdateCoach)
                                    <button class="btn btn-sm btn-light border text-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editCoachModal{{ $coach->id }}" title="Edit Coach">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if($canUpdateCoach)
                        <div class="modal fade" id="editCoachModal{{ $coach->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <form method="POST" action="{{ route('admin.coaches.update', $coach) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header bg-light border-bottom-0">
                                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Coach</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="mb-3">
                                                <label class="form-label text-muted small fw-semibold">Nama Coach <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="form-control bg-light" value="{{ $coach->name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted small fw-semibold">Email <span class="text-danger">*</span></label>
                                                <input type="email" name="email" class="form-control bg-light" value="{{ $coach->email }}" required>
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
                        <td colspan="4" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64" class="opacity-50 mb-3">
                            <h6 class="text-muted mb-0">Belum ada Coach terdaftar.</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($coaches->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $coaches->links() }}
            </div>
        @endif
    </div>
</div>

@if($canCreateCoach)
<div class="modal fade" id="addCoachModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('admin.coaches.store') }}">
                @csrf
                <div class="modal-header bg-primary text-white border-bottom-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Tambah Coach Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Nama Coach <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-light" value="{{ old('name') }}" required placeholder="Contoh: Coach Budi">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control bg-light" value="{{ old('email') }}" required placeholder="coach@contoh.com">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control bg-light" required minlength="6" placeholder="Min. 6 karakter">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Konfirmasi Password <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control bg-light" required placeholder="Ulangi password">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-medium">Buat Coach</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
