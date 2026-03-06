<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Learning Report System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

{{-- NAVIGASI --}}
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">📚 LRS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                @auth
                    {{-- Menu untuk Coach --}}
                    @if(auth()->user()->role === 'coach')
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('coach.reports.index') }}">Laporan Saya</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('coach.reports.create') }}">Submit Laporan</a>
                        </li>

                    {{-- Menu untuk Admin --}}
                    @elseif(auth()->user()->role === 'admin')
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.reports.index') }}">Laporan</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                Master Data
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="{{ route('admin.schools.index') }}">Sekolah</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('admin.classes.index') }}">Kelas</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('admin.coaches.index') }}">Coach</a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item" href="{{ route('admin.users.index') }}">Manajemen Akun</a></li>

                            </ul>
                        </li>

                    {{-- Menu untuk School PIC --}}
                    @elseif(auth()->user()->role === 'school_pic')
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('pic.dashboard') }}">Dashboard</a>
                        </li>
                    @endif
                @endauth
            </ul>

            {{-- Menu User (kanan atas) --}}
            @auth
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        {{ auth()->user()->name }}
                        <span class="badge bg-light text-dark ms-1">{{ ucfirst(auth()->user()->role) }}</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger" type="submit">
                                    🚪 Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
            @endauth
        </div>
    </div>
</nav>

{{-- KONTEN UTAMA --}}
<main>
    {{-- Flash message sukses --}}
    @if(session('success'))
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show py-2">
                ✅ {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    {{-- Flash message error --}}
    @if(session('error'))
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show py-2">
                ❌ {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts') {{-- untuk JS tambahan per halaman --}}
</body>
</html>