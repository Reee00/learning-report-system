@extends('layouts.app')
@section('title', 'Manajemen Akun')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-people-fill text-primary me-2"></i> Manajemen Akun</h4>
            <p class="text-muted small mb-0">Kelola akses pengguna, role, dan penugasan sekolah.</p>
        </div>
        <button class="btn btn-primary shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalTambahAkun">
            <i class="bi bi-person-plus-fill"></i> Tambah Akun
        </button>
    </div>

    {{-- Filter --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label text-muted small fw-semibold">Cari Nama / Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" value="{{ request('search') }}" placeholder="Ketik nama atau email...">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold">Filter Role</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-person-badge"></i></span>
                        <select name="role" class="form-select border-start-0 ps-0">
                            <option value="">Semua Role</option>
                            @foreach(\App\Models\User::roleLabels() as $roleKey => $roleLabel)
                                <option value="{{ $roleKey }}" {{ request('role') === $roleKey ? 'selected' : '' }}>{{ $roleLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary flex-fill d-flex justify-content-center align-items-center gap-2">
                        Filter
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-light border d-flex justify-content-center align-items-center gap-2" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel Akun --}}
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary fw-semibold text-center" style="width: 50px;">#</th>
                        <th class="text-secondary fw-semibold">Pengguna</th>
                        <th class="text-secondary fw-semibold">Role</th>
                        <th class="text-secondary fw-semibold">Penugasan Sekolah</th>
                        <th class="text-center text-secondary fw-semibold" style="width: 250px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="text-center text-muted small">{{ $loop->iteration + ($users->currentPage() - 1) * $users->perPage() }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                    {{ substr($user->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark d-flex align-items-center">
                                        {{ $user->name }}
                                        @if($user->id === auth()->id())
                                            <span class="badge bg-secondary ms-2 small" style="font-size: 0.65rem;">Anda</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $user->roleBadgeColor() }}-subtle text-{{ $user->roleBadgeColor() }} border border-{{ $user->roleBadgeColor() }}-subtle px-3 py-1">
                                {{ $user->roleLabel() }}
                            </span>
                        </td>
                        <td>
                            @if($user->isSchoolScoped() && $user->schools->isNotEmpty())
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($user->schools as $assignedSchool)
                                        <span class="badge bg-light text-dark border border-secondary-subtle">
                                            <i class="bi bi-building text-muted me-1"></i> {{ $assignedSchool->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted small fst-italic">Global / Tidak Ada</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex justify-content-center gap-2">
                                {{-- Tombol Edit --}}
                                <button class="btn btn-sm btn-light border text-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $user->id }}" title="Edit User">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>

                                {{-- Tombol Reset Password --}}
                                <button class="btn btn-sm btn-light border text-warning rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalReset{{ $user->id }}" title="Reset Password">
                                    <i class="bi bi-key-fill"></i>
                                </button>

                                {{-- Tombol Hapus (tidak tampil untuk akun sendiri) --}}
                                @if($user->id !== auth()->id())
                                    <button class="btn btn-sm btn-light border text-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalHapus{{ $user->id }}" title="Hapus User">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- ===== MODAL EDIT ===== --}}
                    <div class="modal fade" id="modalEdit{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                    @csrf @method('PUT')
                                    <div class="modal-header bg-light border-bottom-0">
                                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Akun</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Nama Lengkap</label>
                                            <input type="text" name="name" class="form-control bg-light" value="{{ $user->name }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Email</label>
                                            <input type="email" name="email" class="form-control bg-light" value="{{ $user->email }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Role</label>
                                            <select name="role" class="form-select bg-light" onchange="toggleSchoolField(this, 'editSchool{{ $user->id }}')">
                                                @foreach(\App\Models\User::roleLabels() as $roleKey => $roleLabel)
                                                    <option value="{{ $roleKey }}" {{ $user->role === $roleKey ? 'selected' : '' }}>{{ $roleLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3" id="editSchool{{ $user->id }}" style="{{ $user->isSchoolScoped() ? '' : 'display:none' }}">
                                            <label class="form-label text-muted small fw-semibold">Sekolah Scope <span class="text-danger">*</span></label>
                                            @php($assignedSchoolIds = $user->assignedSchoolIds())
                                            <select name="school_ids[]" class="form-select bg-light" multiple size="4">
                                                @foreach($schools as $school)
                                                    <option value="{{ $school->id }}" {{ in_array($school->id, $assignedSchoolIds, true) ? 'selected' : '' }}>
                                                        {{ $school->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-text small"><i class="bi bi-info-circle"></i> Gunakan Ctrl/Cmd untuk memilih beberapa sekolah.</div>
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

                    {{-- ===== MODAL RESET PASSWORD ===== --}}
                    <div class="modal fade" id="modalReset{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                                    @csrf @method('PATCH')
                                    <div class="modal-header bg-warning text-dark border-bottom-0">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-key-fill me-2"></i> Reset Password</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <p class="text-muted mb-4">Anda akan mereset password untuk pengguna <strong>{{ $user->name }}</strong>.</p>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Password Baru</label>
                                            <input type="password" name="password" class="form-control bg-light" required minlength="6" placeholder="Minimal 6 karakter">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-semibold">Konfirmasi Password Baru</label>
                                            <input type="password" name="password_confirmation" class="form-control bg-light" required placeholder="Ulangi password baru">
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light border-top-0">
                                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-warning px-4 fw-medium">Reset Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- ===== MODAL HAPUS ===== --}}
                    @if($user->id !== auth()->id())
                    <div class="modal fade" id="modalHapus{{ $user->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-body p-5 text-center">
                                    <div class="bg-danger-subtle text-danger rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px;">
                                        <i class="bi bi-trash-fill fs-1"></i>
                                    </div>
                                    <h4 class="fw-bold mb-3">Hapus Pengguna?</h4>
                                    <p class="text-muted mb-4">Apakah Anda yakin ingin menghapus <strong>{{ $user->name }}</strong>? Tindakan ini tidak bisa dibatalkan dan semua data terkait akan terhapus.</p>
                                    <div class="d-flex justify-content-center gap-3">
                                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger px-4 fw-medium">Ya, Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64" class="opacity-50 mb-3">
                            <h6 class="text-muted mb-0">Tidak ada akun ditemukan.</h6>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="card-footer bg-white py-3 border-top-0">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>

{{-- ===== MODAL TAMBAH AKUN ===== --}}
<div class="modal fade" id="modalTambahAkun" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="modal-header bg-primary text-white border-bottom-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Tambah Akun Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control bg-light" value="{{ old('name') }}" required placeholder="Contoh: Budi Santoso">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control bg-light" value="{{ old('email') }}" required placeholder="email@contoh.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select bg-light" required onchange="toggleSchoolField(this, 'tambahSchoolField')">
                            <option value="">— Pilih Role —</option>
                            @foreach(\App\Models\User::roleLabels() as $roleKey => $roleLabel)
                                <option value="{{ $roleKey }}" {{ old('role') === $roleKey ? 'selected' : '' }}>{{ $roleLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Field sekolah --}}
                    <div class="mb-3" id="tambahSchoolField" style="{{ in_array(old('role'), \App\Models\User::schoolScopedRoles(), true) ? '' : 'display:none' }}">
                        <label class="form-label text-muted small fw-semibold">Penugasan Sekolah <span class="text-danger">*</span></label>
                        <select name="school_ids[]" class="form-select bg-light" multiple size="4">
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" {{ in_array($school->id, (array) old('school_ids', [])) ? 'selected' : '' }}>
                                    {{ $school->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text small"><i class="bi bi-info-circle"></i> Wajib untuk School PIC dan Finance; bisa memilih lebih dari satu (Ctrl/Cmd+Klik).</div>
                    </div>

                    <div class="row g-3 mb-3">
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
                    <button type="submit" class="btn btn-primary px-4 fw-medium">Buat Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const SCHOOL_SCOPED_ROLES = @json(\App\Models\User::schoolScopedRoles());

function toggleSchoolField(selectEl, targetId) {
    const field = document.getElementById(targetId);
    if (SCHOOL_SCOPED_ROLES.includes(selectEl.value)) {
        field.style.display = 'block';
    } else {
        field.style.display = 'none';
        // Clear selection if hidden
        const select = field.querySelector('select');
        if (select) {
            Array.from(select.options).forEach(opt => opt.selected = false);
        }
    }
}
</script>
@endsection
