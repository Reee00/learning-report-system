@extends('layouts.app')
@section('title', 'Pengaturan Akun')

@section('content')
@php
    $currentUser = auth()->user();
@endphp

<div class="container py-4">
    <div class="mb-4">
        <h1 class="page-title">Pengaturan Akun</h1>
        <p class="text-muted small mb-0">
            Perbarui profil, nomor WhatsApp, dan password Anda. Perubahan hanya berlaku untuk akun Anda sendiri.
        </p>
    </div>

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
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <span class="fw-bold text-dark"><i class="bi bi-person-vcard text-primary me-2"></i> Data Akun</span>
                </div>
                <form method="POST" action="{{ route('account.update') }}">
                    @csrf
                    @method('PATCH')
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label for="accountName" class="form-label text-muted small fw-semibold">
                                Nama <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="accountName" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}" required maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="accountEmail" class="form-label text-muted small fw-semibold">Email</label>
                            <input type="email" id="accountEmail" class="form-control bg-light"
                                   value="{{ $user->email }}" readonly disabled>
                            <div class="form-text small">
                                <i class="bi bi-info-circle"></i>
                                Email adalah identitas login dan tidak dapat diubah dari halaman ini.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="accountWhatsapp" class="form-label text-muted small fw-semibold">Nomor WhatsApp</label>
                            <input type="text" id="accountWhatsapp" name="whatsapp"
                                   class="form-control @error('whatsapp') is-invalid @enderror"
                                   value="{{ old('whatsapp', $user->whatsapp) }}"
                                   inputmode="tel" autocomplete="tel"
                                   placeholder="Contoh: 081234567890">
                            @error('whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text small">
                                <i class="bi bi-info-circle"></i>
                                Format <code>08xxxxxxxxxx</code>. Boleh juga menulis <code>+62…</code> — sistem menyesuaikan sendiri.
                                Kosongkan bila tidak ingin menampilkan nomor.
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-top-0 py-3 d-flex justify-content-end gap-2">
                        <a href="{{ url()->previous() }}" class="btn btn-light border px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4 fw-medium">
                            <i class="bi bi-save me-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <span class="fw-bold text-dark"><i class="bi bi-shield-lock text-primary me-2"></i> Ringkasan</span>
                </div>
                <div class="card-body p-4">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted fw-semibold">Peran</dt>
                        <dd class="col-7">
                            <span class="badge bg-{{ $user->roleBadgeColor() }}">{{ $user->roleLabel() }}</span>
                        </dd>

                        <dt class="col-5 text-muted fw-semibold">Nomor WhatsApp</dt>
                        <dd class="col-7">
                            @include('partials.whatsapp-link', ['person' => $user])
                        </dd>
                    </dl>
                    <hr>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-eye-slash me-1"></i>
                        Nomor WhatsApp Anda hanya ditampilkan pada halaman ini dan kepada
                        SuperAdmin, Relation, SPV Coach, serta PIC sekolah Anda.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Ganti password: formulir TERPISAH dari profil supaya kegagalan
         konfirmasi password tidak pernah membatalkan penyimpanan nama/nomor.
         Error bag `password` menjaga pesannya tidak bocor ke kartu profil. --}}
    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <span class="fw-bold text-dark"><i class="bi bi-key text-primary me-2"></i> Ganti Password</span>
                </div>
                <form method="POST" action="{{ route('account.password.update') }}">
                    @csrf
                    @method('PATCH')
                    <div class="card-body p-4">
                        @if ($errors->password->any())
                            <div class="alert alert-danger border-0 small">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->password->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="currentPassword" class="form-label text-muted small fw-semibold">
                                Password Saat Ini <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" id="currentPassword" name="current_password"
                                       class="form-control border-end-0 @if($errors->password->has('current_password')) is-invalid @endif"
                                       required autocomplete="current-password">
                                <button class="btn btn-outline-secondary border-start-0" type="button"
                                        data-password-toggle="#currentPassword"
                                        aria-controls="currentPassword" aria-pressed="false"
                                        aria-label="Tampilkan password saat ini"
                                        title="Tampilkan password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="newPassword" class="form-label text-muted small fw-semibold">
                                Password Baru <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" id="newPassword" name="password"
                                       class="form-control border-end-0 @if($errors->password->has('password')) is-invalid @endif"
                                       required minlength="6" autocomplete="new-password">
                                <button class="btn btn-outline-secondary border-start-0" type="button"
                                        data-password-toggle="#newPassword"
                                        aria-controls="newPassword" aria-pressed="false"
                                        aria-label="Tampilkan password baru"
                                        title="Tampilkan password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="form-text small">
                                <i class="bi bi-info-circle"></i> Minimal 6 karakter.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="confirmPassword" class="form-label text-muted small fw-semibold">
                                Konfirmasi Password Baru <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password" id="confirmPassword" name="password_confirmation"
                                       class="form-control border-end-0" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary border-start-0" type="button"
                                        data-password-toggle="#confirmPassword"
                                        aria-controls="confirmPassword" aria-pressed="false"
                                        aria-label="Tampilkan konfirmasi password"
                                        title="Tampilkan password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-top-0 py-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4 fw-medium">
                            <i class="bi bi-shield-check me-1"></i> Ubah Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@include('partials.password-toggle-scripts')
@endsection
