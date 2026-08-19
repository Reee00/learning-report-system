@extends('layouts.app')
@section('title', 'Kelola Assignment Coach')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canAssignCoach = $currentUser && $authorization->allows($currentUser, 'coaches.assign');
    $canReassignCoach = $currentUser && $authorization->allows($currentUser, 'coaches.reassign');
@endphp

<div class="container py-4">
    <div class="mb-4">
        <a href="{{ route('admin.coaches.index') }}" class="btn btn-light border px-3 py-2 fw-medium text-secondary mb-3">
            <i class="bi bi-arrow-left me-2"></i> Kembali ke Daftar Coach
        </a>
        <div class="d-flex align-items-center bg-white p-4 rounded-3 shadow-sm border border-light">
            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width: 64px; height: 64px; font-size: 1.5rem;">
                {{ substr($coach->name, 0, 1) }}
            </div>
            <div>
                <h4 class="mb-1 fw-bold text-dark">{{ $coach->name }}</h4>
                <p class="text-muted mb-0"><i class="bi bi-envelope me-2"></i>{{ $coach->email }}</p>
            </div>
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

    <div class="row g-4">
        {{-- KOLOM KIRI: Kelas yang sudah di-assign --}}
        <div class="col-md-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <span class="fw-bold fs-6 text-dark">
                        <i class="bi bi-check-circle-fill text-success me-2"></i> Kelas yang Sudah Di-assign
                        <span class="badge bg-primary rounded-pill ms-2">{{ $coach->coachClasses->count() }}</span>
                    </span>
                </div>
                <div class="table-responsive">
                    @if($coach->coachClasses->isEmpty())
                        <div class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64" class="opacity-50 mb-3">
                            <h6 class="text-muted mb-0">Belum ada kelas yang di-assign ke coach ini.</h6>
                        </div>
                    @else
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-secondary fw-semibold ps-4">Sekolah</th>
                                    <th class="text-secondary fw-semibold">Kelas</th>
                                    @if($canReassignCoach)
                                        <th class="text-center text-secondary fw-semibold" style="width: 100px;">Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($coach->coachClasses as $cc)
                                <tr>
                                    <td class="fw-medium text-dark ps-4">
                                        <i class="bi bi-building text-muted me-2"></i>{{ $cc->schoolClass->school->name }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border border-secondary-subtle px-2 py-1">
                                            {{ $cc->schoolClass->name }}
                                        </span>
                                    </td>
                                    @if($canReassignCoach)
                                        <td class="text-center">
                                            <button type="button" onclick="confirmAction('{{ route('admin.coaches.unassign', [$coach, $cc]) }}', 'Apakah Anda yakin ingin menghapus kelas ini dari tugas Coach?')" class="btn btn-sm btn-light border text-danger rounded-pill px-3" title="Hapus Assignment">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN: Assign ke kelas baru --}}
        <div class="col-md-5">
            <div class="position-sticky" style="top: 2rem;">
                <div class="card shadow-sm border-0 border-top border-primary border-4">
                    <div class="card-header bg-white py-3 border-bottom border-light">
                        <span class="fw-bold fs-6 text-dark"><i class="bi bi-plus-circle-fill text-primary me-2"></i> Assign ke Kelas Baru</span>
                    </div>
                    <div class="card-body p-4">
                        @if($availableClasses->isEmpty())
                            <div class="text-center py-4">
                                <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-check-lg fs-4"></i>
                                </div>
                                <h6 class="fw-bold text-success mb-1">Semua Selesai!</h6>
                                <p class="text-muted small mb-0">Semua kelas sudah di-assign ke coach ini.</p>
                            </div>
                        @elseif($canAssignCoach)
                            <form method="POST" action="{{ route('admin.coaches.assign', $coach) }}">
                                @csrf
                                <div class="mb-4">
                                    <label class="form-label text-muted small fw-semibold">Pilih Kelas</label>
                                    <select name="class_id" class="form-select bg-light" required size="6">
                                        <option value="" disabled selected>— Pilih Sekolah & Kelas —</option>
                                        @foreach($availableClasses as $schoolName => $classes)
                                            <optgroup label="{{ $schoolName }}">
                                                @foreach($classes as $class)
                                                    <option value="{{ $class->id }}">
                                                        {{ $class->name }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    <div class="form-text small mt-2"><i class="bi bi-info-circle"></i> Kelas yang sudah di-assign disembunyikan.</div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 fw-medium shadow-sm d-flex align-items-center justify-content-center gap-2">
                                    <i class="bi bi-link-45deg fs-5"></i> Assign Kelas
                                </button>
                            </form>
                        @else
                            <div class="alert alert-warning border-0 mb-0">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                                    <p class="mb-0 small fw-medium">Anda tidak memiliki permission untuk melakukan assignment.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
