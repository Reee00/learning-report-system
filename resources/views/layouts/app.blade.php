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
                    @php
                        $currentUser = auth()->user();
                        $authorization = app(\App\Services\AuthorizationService::class);
                    @endphp
                    {{-- Menu untuk Coach --}}
                    @if($currentUser->role === 'coach')
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('coach.reports.index') }}">Laporan Saya</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('coach.reports.create') }}">Submit Laporan</a>
                        </li>

                    {{-- Menu untuk Relation / SuperAdmin --}}
                    @elseif(in_array($currentUser->role, ['relation', 'superadmin'], true))
                        @if($currentUser->isSuperAdmin())
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.dashboard') }}">Dashboard</a>
                            </li>
                        @endif
                        @if($authorization->allows($currentUser, 'reports.view_all'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.reports.index') }}">Coach Report</a>
                            </li>
                        @endif
                        @if($authorization->allows($currentUser, 'attendance.view'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('attendance.index') }}">Attendance</a>
                            </li>
                        @endif
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                Master Data
                            </a>
                            <ul class="dropdown-menu">
                                @if($authorization->allows($currentUser, 'schools.view'))
                                    <li><a class="dropdown-item" href="{{ route('admin.schools.index') }}">Sekolah</a></li>
                                @endif
                                @if($authorization->allows($currentUser, 'program_classes.view'))
                                    <li><a class="dropdown-item" href="{{ route('admin.classes.index') }}">Kelas</a></li>
                                @endif
                                @if($authorization->allows($currentUser, 'programs.view'))
                                    <li><a class="dropdown-item" href="{{ route('admin.programs.index') }}">Program</a></li>
                                @endif
                                @if($authorization->allows($currentUser, 'coaches.view'))
                                    <li><a class="dropdown-item" href="{{ route('admin.coaches.index') }}">Coach</a></li>
                                @endif
                                @if($authorization->allows($currentUser, 'users.manage'))
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.users.index') }}">Manajemen Akun</a></li>
                                @endif

                            </ul>
                        </li>

                    {{-- Menu untuk SPV Coach --}}
                    @elseif($currentUser->role === 'spv_coach')
                        @if($authorization->allows($currentUser, 'coaches.view'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.coaches.index') }}">Coach</a>
                            </li>
                        @endif
                        @if($authorization->allows($currentUser, 'reports.view_all'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.reports.index') }}">Coach Report</a>
                            </li>
                        @endif
                        @if($authorization->allows($currentUser, 'attendance.view'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('attendance.index') }}">Attendance</a>
                            </li>
                        @endif

                    {{-- Menu untuk School PIC --}}
                    @elseif($currentUser->role === 'school_pic')
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('pic.dashboard') }}">Dashboard</a>
                        </li>
                        @if($authorization->allows($currentUser, 'reports.view_all'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.reports.index') }}">Coach Report</a>
                            </li>
                        @endif
                        @if($authorization->allows($currentUser, 'attendance.view'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('attendance.index') }}">Attendance</a>
                            </li>
                        @endif

                    {{-- Menu untuk Teacher School --}}
                    @elseif($currentUser->role === 'teacher_school')
                        @if($authorization->allows($currentUser, 'reports.view_all'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.reports.index') }}">Coach Report</a>
                            </li>
                        @endif
                        @if($authorization->allows($currentUser, 'attendance.view'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('attendance.index') }}">Attendance</a>
                            </li>
                        @endif

                    {{-- Menu untuk Finance --}}
                    @elseif($currentUser->role === 'finance')
                        @if($authorization->allows($currentUser, 'attendance.view'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('attendance.index') }}">Attendance</a>
                            </li>
                        @endif
                    @endif
                @endauth
            </ul>

            {{-- Menu User (kanan atas) --}}
            @auth
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        {{ auth()->user()->name }}
                        {{-- Label role dibaca dari User::roleLabels() agar tidak pernah
                             beda dengan Manajemen Akun (mis. "Spv coach" vs "SPV Coach"). --}}
                        <span class="badge bg-light text-dark ms-1">{{ auth()->user()->roleLabel() }}</span>
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
