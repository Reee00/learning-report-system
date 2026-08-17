@extends('layouts.app')
@section('title', 'Manajemen Akun')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">👤 Manajemen Akun</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahAkun">
            + Tambah Akun
        </button>
    </div>

    {{-- Filter --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Cari Nama / Email</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           value="{{ request('search') }}" placeholder="Ketik nama atau email...">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Filter Role</label>
                    <select name="role" class="form-select form-select-sm">
                        <option value="">Semua Role</option>
                        @foreach(\App\Models\User::roleLabels() as $roleKey => $roleLabel)
                            <option value="{{ $roleKey }}" {{ request('role') === $roleKey ? 'selected' : '' }}>{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-fill">Cari</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel Akun --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Sekolah (Scope)</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            {{ $user->name }}
                            @if($user->id === auth()->id())
                                <span class="badge bg-secondary ms-1">Anda</span>
                            @endif
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <span class="badge bg-{{ $user->roleBadgeColor() }}">
                                {{ $user->roleLabel() }}
                            </span>
                        </td>
                        <td>
                            @if($user->isSchoolScoped() && $user->schools->isNotEmpty())
                                @foreach($user->schools as $assignedSchool)
                                    <span class="badge bg-success me-1 mb-1">{{ $assignedSchool->name }}</span>
                                @endforeach
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                {{-- Tombol Edit --}}
                                <button class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEdit{{ $user->id }}">
                                    Edit
                                </button>

                                {{-- Tombol Reset Password --}}
                                <button class="btn btn-sm btn-outline-warning"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalReset{{ $user->id }}">
                                    Reset PW
                                </button>

                                {{-- Tombol Hapus (tidak tampil untuk akun sendiri) --}}
                                @if($user->id !== auth()->id())
                                    <button class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalHapus{{ $user->id }}">
                                        Hapus
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- ===== MODAL EDIT ===== --}}
                    <div class="modal fade" id="modalEdit{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                    @csrf @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Akun</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Lengkap</label>
                                            <input type="text" name="name" class="form-control"
                                                   value="{{ $user->name }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control"
                                                   value="{{ $user->email }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <select name="role" class="form-select"
                                                    onchange="toggleSchoolField(this, 'editSchool{{ $user->id }}')">
                                                @foreach(\App\Models\User::roleLabels() as $roleKey => $roleLabel)
                                                    <option value="{{ $roleKey }}" {{ $user->role === $roleKey ? 'selected' : '' }}>{{ $roleLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3" id="editSchool{{ $user->id }}"
                                             style="{{ $user->isSchoolScoped() ? '' : 'display:none' }}">
                                            <label class="form-label">Sekolah Scope <span class="text-danger">*</span></label>
                                            @php($assignedSchoolIds = $user->assignedSchoolIds())
                                            <select name="school_ids[]" class="form-select" multiple size="4">
                                                @foreach($schools as $school)
                                                    <option value="{{ $school->id }}"
                                                        {{ in_array($school->id, $assignedSchoolIds, true) ? 'selected' : '' }}>
                                                        {{ $school->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">Gunakan Ctrl/Cmd untuk memilih beberapa sekolah.</div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- ===== MODAL RESET PASSWORD ===== --}}
                    <div class="modal fade" id="modalReset{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                                    @csrf @method('PATCH')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reset Password — {{ $user->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Password Baru</label>
                                            <input type="password" name="password"
                                                   class="form-control" required minlength="6"
                                                   placeholder="Minimal 6 karakter">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Konfirmasi Password Baru</label>
                                            <input type="password" name="password_confirmation"
                                                   class="form-control" required
                                                   placeholder="Ulangi password baru">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-warning">Reset Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- ===== MODAL HAPUS ===== --}}
                    @if($user->id !== auth()->id())
                    <div class="modal fade" id="modalHapus{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title">⚠️ Hapus Akun</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Apakah kamu yakin ingin menghapus akun:</p>
                                    <p class="fw-bold">{{ $user->name }} ({{ $user->email }})</p>
                                    <p class="text-danger small">
                                        ⚠️ Tindakan ini tidak bisa dibatalkan. Semua data terkait akun ini akan ikut terhapus.
                                    </p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger">Ya, Hapus Akun</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Tidak ada akun ditemukan.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="card-footer">{{ $users->links() }}</div>
        @endif
    </div>
</div>

{{-- ===== MODAL TAMBAH AKUN ===== --}}
<div class="modal fade" id="modalTambahAkun" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Akun Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               value="{{ old('name') }}" required
                               placeholder="Contoh: Budi Santoso">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email') }}" required
                               placeholder="email@contoh.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required
                                onchange="toggleSchoolField(this, 'tambahSchoolField')">
                            <option value="">— Pilih Role —</option>
                            @foreach(\App\Models\User::roleLabels() as $roleKey => $roleLabel)
                                <option value="{{ $roleKey }}" {{ old('role') === $roleKey ? 'selected' : '' }}>{{ $roleLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Field sekolah, hanya muncul untuk role yang di-scope per sekolah --}}
                    <div class="mb-3" id="tambahSchoolField"
                         style="{{ in_array(old('role'), \App\Models\User::schoolScopedRoles(), true) ? '' : 'display:none' }}">
                        <label class="form-label">Sekolah Scope <span class="text-danger">*</span></label>
                        <select name="school_ids[]" class="form-select" multiple size="4">
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}"
                                    {{ in_array($school->id, (array) old('school_ids', [])) ? 'selected' : '' }}>
                                    {{ $school->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Wajib untuk School PIC dan Finance; bisa memilih lebih dari satu.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control"
                               required minlength="6" placeholder="Minimal 6 karakter">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control"
                               required placeholder="Ulangi password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Buat Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// Tampilkan/sembunyikan field sekolah berdasarkan role yang dipilih.
// Daftar role berasal dari backend agar tidak pernah beda dengan validasi.
const SCHOOL_SCOPED_ROLES = @json(\App\Models\User::schoolScopedRoles());

function toggleSchoolField(selectEl, targetId) {
    const field = document.getElementById(targetId);
    field.style.display = SCHOOL_SCOPED_ROLES.includes(selectEl.value) ? 'block' : 'none';
}
</script>
@endsection
