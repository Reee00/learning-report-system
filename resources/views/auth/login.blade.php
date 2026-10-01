<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Login — Learning Report System</title>

    {{-- PWA: manifest, ikon, dan meta installable --}}
    @include('partials.pwa-head')

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons: dipakai ikon mata pada tombol tampil/sembunyikan
         password. Halaman login berdiri sendiri (tidak memakai layouts.app),
         jadi stylesheet-nya dimuat di sini juga. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        html { min-width: 320px; }
        body { min-height: 100dvh !important; padding: .75rem 0; }
        .container { width: 100%; padding-inline: .75rem; }
        .form-control { min-height: 48px; font-size: 16px; }
        .btn { min-height: 44px; }
        @media (max-width: 359.98px) {
            .container { padding-inline: .5rem; }
            .card-body { padding: 1rem !important; }
        }
    </style>
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh">

<div class="container" style="max-width: 420px">
    <div class="card shadow-sm mt-5">
        <div class="card-body p-4">
            <h1 class="h4 card-title text-center mb-1">Learning Report System</h1>
            <p class="text-center text-muted mb-4 small">Masuk ke akun Anda</p>

            {{-- Sesi kedaluwarsa / logout di tab lain mengirim user ke sini
                 dengan pesan `error`. Tanpa blok ini pesannya tidak pernah
                 terlihat dan user hanya melihat form login tanpa penjelasan
                 kenapa ia tiba-tiba diminta masuk lagi. --}}
            @if (session('error'))
                <div class="alert alert-warning py-2" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-info py-2" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Tampilkan error jika ada --}}
            @if ($errors->any())
                <div class="alert alert-danger py-2" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf {{-- Token keamanan, wajib ada di setiap form --}}

                <div class="mb-3">
                    <label class="form-label" for="loginEmail">Email</label>
                    <input
                        type="email"
                        id="loginEmail"
                        name="email"
                        class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="contoh@lrs.com"
                    >
                </div>

                {{-- UX login 2026-10-01: label memakai kata biasa "Password" dan
                     TIDAK diulang sebagai placeholder — label sudah menjelaskan
                     field-nya, placeholder yang mengulang hanya menambah bunyi.
                     Field tetap `type="password"` sehingga karakter termasking
                     sejak awal, dan password manager browser tetap bekerja lewat
                     `autocomplete="current-password"`. --}}
                <div class="mb-4">
                    <label class="form-label" for="loginPassword">Password</label>
                    <div class="input-group">
                        <input
                            type="password"
                            id="loginPassword"
                            name="password"
                            class="form-control border-end-0 @error('password') is-invalid @enderror"
                            required
                            autocomplete="current-password"
                        >
                        {{-- Tombol ini TIDAK pernah men-submit form: `type="button"`,
                             dan form hanya terkirim lewat tombol "Masuk". --}}
                        <button
                            class="btn btn-outline-secondary border-start-0"
                            type="button"
                            data-password-toggle="#loginPassword"
                            aria-controls="loginPassword"
                            aria-pressed="false"
                            aria-label="Tampilkan password"
                            title="Tampilkan password"
                        >
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Masuk
                </button>
            </form>
        </div>
    </div>
</div>

@include('partials.password-toggle-scripts')

{{-- PWA: registrasi service worker (tanpa install prompt di halaman login) --}}
@include('partials.pwa-scripts')

</body>
</html>
