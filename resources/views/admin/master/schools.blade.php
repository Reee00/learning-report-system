@extends('layouts.app')
@section('title', 'Master Sekolah')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>🏫 Master Data Sekolah</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSchoolModal">
            + Tambah Sekolah
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama Sekolah</th>
                        <th>PIC</th>
                        <th>Jumlah Kelas</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($schools as $school)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            {{ $school->name }}<br>
                            <small class="text-muted">{{ $school->address }}</small>
                        </td>
                        <td>{{ $school->pic_name ?? '-' }}</td>
                        <td>{{ $school->classes_count }}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editSchoolModal{{ $school->id }}">
                                Edit
                            </button>
                            <form method="POST" action="{{ route('admin.schools.destroy', $school) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Hapus sekolah ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>

                    {{-- Modal Edit --}}
                    <div class="modal fade" id="editSchoolModal{{ $school->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.schools.update', $school) }}">
                                    @csrf @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Sekolah</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Sekolah</label>
                                            <input type="text" name="name" class="form-control"
                                                   value="{{ $school->name }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Alamat</label>
                                            <textarea name="address" class="form-control" rows="2">{{ $school->address }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Nama PIC</label>
                                            <input type="text" name="pic_name" class="form-control"
                                                   value="{{ $school->pic_name }}">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Belum ada sekolah.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($schools->hasPages())
            <div class="card-footer">{{ $schools->links() }}</div>
        @endif
    </div>
</div>

{{-- Modal Tambah Sekolah --}}
<div class="modal fade" id="addSchoolModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.schools.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Sekolah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Sekolah *</label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="Contoh: SD Negeri 1 Jakarta">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="address" class="form-control" rows="2"
                                  placeholder="Alamat sekolah (opsional)"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama PIC</label>
                        <input type="text" name="pic_name" class="form-control"
                               placeholder="Nama penanggung jawab di sekolah">
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