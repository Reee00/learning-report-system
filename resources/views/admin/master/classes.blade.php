@extends('layouts.app')
@section('title', 'Master Kelas')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>🏛️ Master Data Kelas</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
            + Tambah Kelas
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama Kelas</th>
                        <th>Sekolah</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($classes as $class)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $class->name }}</td>
                        <td>{{ $class->school->name }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.classes.destroy', $class) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Hapus kelas ini? Semua data terkait akan ikut terhapus.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                        <td>
    <!-- TAMBAHKAN TOMBOL INI -->
    <a href="{{ route('students.show', $class) }}"
       class="btn btn-sm btn-outline-info">
        👥 Siswa
    </a>

    <form method="POST" action="{{ route('admin.classes.destroy', $class) }}"
          class="d-inline"
          onsubmit="return confirm('Hapus kelas ini?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger">Hapus</button>
    </form>
</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Belum ada kelas.</td>
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

{{-- Modal Tambah Kelas --}}
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.classes.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Sekolah *</label>
                        <select name="school_id" class="form-select" required>
                            <option value="">— Pilih Sekolah —</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}">{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Kelas *</label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="Contoh: Grade 5A, Kelas 3B">
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
@endsection