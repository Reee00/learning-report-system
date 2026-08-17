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
        <h4>Master Data Coach</h4>
        @if($canCreateCoach)
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCoachModal">
                + Tambah Coach
            </button>
        @endif
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama Coach</th>
                        <th>Email</th>
                        <th>Kelas yang Di-assign</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($coaches as $coach)
                    <tr>
                        <td>{{ ($coaches->currentPage() - 1) * $coaches->perPage() + $loop->iteration }}</td>
                        <td>{{ $coach->name }}</td>
                        <td>{{ $coach->email }}</td>
                        <td>
                            @if($coach->coachClasses->isEmpty())
                                <span class="text-muted fst-italic">Belum ada assignment</span>
                            @else
                                @foreach($coach->coachClasses as $assignment)
                                    <span class="badge bg-primary me-1 mb-1">
                                        {{ $assignment->schoolClass->school->name }} - {{ $assignment->schoolClass->name }}
                                    </span>
                                @endforeach
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.coaches.show', $coach) }}"
                               class="btn btn-sm btn-outline-primary">
                                Kelola Assignment
                            </a>
                            @if($canUpdateCoach)
                                <button class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editCoachModal{{ $coach->id }}">
                                    Edit
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if($canUpdateCoach)
                        <div class="modal fade" id="editCoachModal{{ $coach->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('admin.coaches.update', $coach) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Coach</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Nama Coach</label>
                                                <input type="text" name="name" class="form-control"
                                                       value="{{ $coach->name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="email" class="form-control"
                                                       value="{{ $coach->email }}" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary">Simpan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Belum ada Coach terdaftar.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($coaches->hasPages())
            <div class="card-footer">{{ $coaches->links() }}</div>
        @endif
    </div>
</div>

@if($canCreateCoach)
<div class="modal fade" id="addCoachModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.coaches.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Coach</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Coach *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password *</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambah Coach</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
