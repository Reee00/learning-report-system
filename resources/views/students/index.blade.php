@extends('layouts.app')
@section('title', 'Data Siswa — ' . $class->name)

@section('content')
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $canCreateStudents = $currentUser && $authorization->allows($currentUser, 'students.create');
    $canDeleteStudents = $currentUser && $authorization->allows($currentUser, 'students.delete');
@endphp
<div class="container py-4">

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

    {{-- Header --}}
    <div class="mb-4">
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm mb-2">
            ← Kembali
        </a>
        <h4 class="mb-0">👥 Data Siswa</h4>
        <p class="text-muted mb-0">
            {{ $class->school->name }} — <strong>{{ $class->name }}</strong>
        </p>
    </div>

    <div class="row g-4">

        {{-- KOLOM KIRI: Daftar Siswa --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        Daftar Siswa
                        <span class="badge bg-primary ms-1">{{ $students->total() }}</span>
                    </span>
                </div>

                <div class="card-body border-bottom bg-light p-3">
                    <form action="{{ route('students.show', $class) }}" method="GET" class="mb-0">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama siswa..." value="{{ request('search') }}">
                            @if(request('search'))
                                <a href="{{ route('students.show', $class) }}" class="btn btn-outline-secondary" title="Reset Search">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                            <button type="submit" class="btn btn-primary px-3 fw-medium">Search</button>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    @if($students->isEmpty())
                        <div class="text-center text-muted py-5">
                            <div class="fs-1">👤</div>
                            <p>Belum ada siswa di kelas ini.</p>
                            <p class="small">Tambahkan siswa secara manual atau upload Excel.</p>
                        </div>
                    @else
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">#</th>
                                    <th>Nama Siswa</th>
                                    @if($canDeleteStudents)
                                        <th width="80">Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($students as $student)
                                <tr>
                                    <td class="text-muted">
                                        {{ ($students->currentPage() - 1) * $students->perPage() + $loop->iteration }}
                                    </td>
                                    <td>{{ $student->name }}</td>
                                    @if($canDeleteStudents)
                                        <td>
                                            <button type="button" onclick="confirmAction('{{ route('students.destroy', [$class, $student]) }}', 'Apakah Anda yakin ingin menghapus siswa {{ addslashes($student->name) }} dari kelas ini?')" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                @if($students->hasPages())
                    <div class="card-footer">{{ $students->links() }}</div>
                @endif
            </div>
        </div>

        @if($canCreateStudents)
        {{-- KOLOM KANAN: Form Tambah --}}
        <div class="col-md-4">

            {{-- Form Tambah Manual --}}
            <div class="card mb-3">
                <div class="card-header fw-semibold">✏️ Tambah Siswa Manual</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('students.store', $class) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nama Siswa</label>
                            <input type="text"
                                   name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}"
                                   placeholder="Contoh: Andi Pratama"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            + Tambah Siswa
                        </button>
                    </form>
                </div>
            </div>

            {{-- Form Upload Excel --}}
            <div class="card">
                <div class="card-header fw-semibold">📊 Upload Excel / CSV</div>
                <div class="card-body">
                    <form method="POST"
                          action="{{ route('students.import', $class) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Pilih File</label>
                            <input type="file"
                                   name="file"
                                   class="form-control @error('file') is-invalid @enderror"
                                   accept=".xlsx,.xls,.csv"
                                   required>
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Format: .xlsx, .xls, atau .csv. Maks 2MB.</div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 mb-2">
                            📤 Upload & Import
                        </button>

                        <a href="{{ route('students.template') }}"
                           class="btn btn-outline-secondary w-100 btn-sm">
                            ⬇️ Download Template Excel
                        </a>
                    </form>

                    {{-- Petunjuk format --}}
                    <hr>
                    <p class="small text-muted mb-1"><strong>Format kolom Excel:</strong></p>
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-light">
                            <tr><th>nama_siswa</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Andi Pratama</td></tr>
                            <tr><td>Bela Sari</td></tr>
                            <tr><td>Citra Dewi</td></tr>
                        </tbody>
                    </table>
                    <p class="small text-muted mt-1 mb-0">
                        ⚠️ Baris pertama harus berisi header <code>nama_siswa</code>.<br>
                        Nama yang sudah ada tidak akan di-duplikat.
                    </p>
                </div>
            </div>

        </div>
        @endif
    </div>
</div>
@endsection
