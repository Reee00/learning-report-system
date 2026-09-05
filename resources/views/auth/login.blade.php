<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Learning Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <h4 class="card-title text-center mb-1">📚 Learning Report System</h4>
            <p class="text-center text-muted mb-4 small">Masuk ke akun Anda</p>

            {{-- Tampilkan error jika ada --}}
            @if ($errors->any())
                <div class="alert alert-danger py-2">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf {{-- Token keamanan, wajib ada di setiap form --}}

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        placeholder="contoh@email.com"
                    >
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        required
                        placeholder="••••••••"
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    Masuk
                </button>
            </form>

            <hr class="mt-4">
            <p class="text-center text-muted small mb-0">
                Relation: admin@lrs.com / SuperAdmin: superadmin@lrs.com<br>
                Coach: coach@lrs.com / PIC: pic@lrs.com<br>
                Password: <code>password</code>
            </p>
        </div>
    </div>
</div>

</body>
</html>
