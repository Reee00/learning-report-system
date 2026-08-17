@extends('layouts.app')
@section('title', 'Master Program Kelas')

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canCreateClass = $currentUser && $authorization->allows($currentUser, 'program_classes.create');
    $canDeleteClass = $currentUser && $authorization->allows($currentUser, 'program_classes.delete');
    $canViewStudents = $currentUser && $authorization->allows($currentUser, 'students.view');
    $hasClassActions = $canDeleteClass || $canViewStudents;
@endphp

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Master Program Kelas</h4>
        @if($canCreateClass)
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                + Tambah Program Kelas
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
                        <th>Nama Program Kelas</th>
                        <th>Sekolah</th>
                        @if($hasClassActions)
                            <th>Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse($classes as $class)
                    <tr>
                        <td>{{ ($classes->currentPage() - 1) * $classes->perPage() + $loop->iteration }}</td>
                        <td>{{ $class->name }}</td>
                        <td>{{ $class->school->name }}</td>
                        @if($hasClassActions)
                            <td>
                                @if($canViewStudents)
                                    <a href="{{ route('students.show', $class) }}"
                                       class="btn btn-sm btn-outline-info">
                                        Siswa
                                    </a>
                                @endif
                                @if($canDeleteClass)
                                    <form method="POST" action="{{ route('admin.classes.destroy', $class) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Hapus program kelas ini? Semua data siswa terkait akan ikut terhapus.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $hasClassActions ? 4 : 3 }}" class="text-center text-muted py-4">
                            Belum ada program kelas.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($classes->hasPages())
            <div class="card-footer">{{ $classes->links() }}</div>
        @endif
    </div>
</div>

@if($canCreateClass)
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.classes.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Program Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Sekolah *</label>
                        <select name="school_id" class="form-select" required>
                            <option value="">Pilih Sekolah</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>
                                    {{ $school->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Program Kelas *</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                               required placeholder="Contoh: Grade 5A, Kelas 3B">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambah</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
