@extends('layouts.app')
@section('title', 'Master Program')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canCreateProgram = $currentUser && $authorization->allows($currentUser, 'programs.create');
    $selectedClassIds = old('class_ids', []);
@endphp

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Master Data Program</h4>
        @if($canCreateProgram)
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                + Tambah Program
            </button>
        @endif
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
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
                        <th>Program</th>
                        <th>Kelas / Sekolah</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($programs as $program)
                    <tr>
                        <td>{{ ($programs->currentPage() - 1) * $programs->perPage() + $loop->iteration }}</td>
                        <td>
                            <strong>{{ $program->name }}</strong>
                            @if($program->code)
                                <br><small class="text-muted">Kode: {{ $program->code }}</small>
                            @endif
                            @if($program->description)
                                <br><small class="text-muted">{{ $program->description }}</small>
                            @endif
                        </td>
                        <td>
                            @forelse($program->programClasses as $programClass)
                                <span class="badge bg-primary me-1 mb-1">
                                    {{ $programClass->schoolClass->school->name }} - {{ $programClass->schoolClass->name }}
                                </span>
                            @empty
                                <span class="text-muted">Belum dikaitkan ke kelas.</span>
                            @endforelse
                        </td>
                        <td>
                            @if($program->status === 'active')
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Tidak Aktif</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Belum ada program.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($programs->hasPages())
            <div class="card-footer">{{ $programs->links() }}</div>
        @endif
    </div>
</div>

@if($canCreateProgram)
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.programs.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Nama Program *</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kode</label>
                            <input type="text" name="code" class="form-control" value="{{ old('code') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="active" @selected(old('status', 'active') === 'active')>Aktif</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Tidak Aktif</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Program Kelas *</label>
                        <select name="class_ids[]" class="form-select" multiple size="8" required>
                            @foreach($classes->groupBy('school.name') as $schoolName => $schoolClasses)
                                <optgroup label="{{ $schoolName }}">
                                    @foreach($schoolClasses as $class)
                                        <option value="{{ $class->id }}"
                                            @selected(in_array($class->id, $selectedClassIds))>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Pilih satu atau lebih kelas. Program yang sama dapat digunakan lintas sekolah melalui association ini.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambah Program</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
