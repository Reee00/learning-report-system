@extends('layouts.app')
@section('title', 'Master Coach')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canCreateCoach = $currentUser && $authorization->allows($currentUser, 'coaches.create');
    $canUpdateCoach = $currentUser && $authorization->allows($currentUser, 'coaches.update');
    // Nomor WhatsApp coach = data kontak pribadi. Controller sudah membuang
    // nilainya di sisi server untuk role tanpa izin ini; flag di sini hanya
    // mengatur apakah kolomnya perlu dirender sama sekali.
    $canViewCoachContact = $currentUser && $authorization->allows($currentUser, 'coaches.contact');
    $columnCount = $canViewCoachContact ? 5 : 4;
@endphp

<div class="container py-4">
    <x-page-header
        title="Master Data Coach"
        description="Kelola daftar coach dan penugasan kelas mereka."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'Coach'],
        ]"
    >
        @if($canCreateCoach)
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCoachModal">
                <i class="bi bi-person-plus" aria-hidden="true"></i> Tambah Coach
            </button>
        @endif
    </x-page-header>

    <x-filter-bar
        :action="route('admin.coaches.index')"
        title="Cari coach"
        :reset="route('admin.coaches.index')"
    >
        <div class="col-md-6">
            <label class="form-label visually-hidden" for="coachSearch">Nama atau email coach</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </span>
                <input
                    type="search"
                    id="coachSearch"
                    name="search"
                    class="form-control border-start-0 ps-0"
                    placeholder="Cari nama coach atau email..."
                    value="{{ request('search') }}"
                >
                <button type="submit" class="btn btn-primary px-4">Cari</button>
            </div>
        </div>
    </x-filter-bar>

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
                        @if($canViewCoachContact)
                            <th class="text-secondary fw-semibold">Nomor WhatsApp</th>
                        @endif
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
                        @if($canViewCoachContact)
                            <td>
                                @include('partials.whatsapp-link', ['person' => $coach])
                            </td>
                        @endif
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
                                <a href="{{ ctx_route('admin.coaches.show', $coach) }}" class="btn btn-sm btn-light border text-primary rounded-pill px-3" title="Kelola Assignment">
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
                                        <input type="hidden" name="_modal" value="edit">
                                        <input type="hidden" name="_modal_id" value="{{ $coach->id }}">
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
                        <td colspan="{{ $columnCount }}" class="text-center py-5">
                            <i class="bi bi-people text-muted opacity-50 mb-3 d-block lh-1" style="font-size: 4rem;" aria-hidden="true"></i>
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
                <input type="hidden" name="_modal" value="add">
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

@section('scripts')
    @include('partials.modal-reopen', ['modalId' => 'addCoachModal', 'when' => old('_modal') === 'add'])
    @include('partials.modal-reopen', ['modalId' => 'editCoachModal'.old('_modal_id'), 'when' => old('_modal') === 'edit'])
@endsection
@endsection
