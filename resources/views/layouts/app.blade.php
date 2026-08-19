<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Learning Report System')</title>
    {{-- Google Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    {{-- Bootstrap 5 & Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    {{-- App Shell Styles --}}
    <style>
        :root {
            --sidebar-width: 256px;
            --sidebar-collapsed-width: 72px;
            --sidebar-bg: #0f172a;
            --sidebar-text: #94a3b8;
            --sidebar-text-hover: #f1f5f9;
            --sidebar-active-bg: rgba(37, 99, 235, 0.15);
            --sidebar-active-text: #60a5fa;
            --sidebar-section-text: #475569;
            --sidebar-border: #1e293b;
            --topbar-height: 56px;
            --content-bg: #f8fafc;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--content-bg);
            color: #334155;
            margin: 0;
            overflow-x: hidden;
        }

        /* ============================================= */
        /* SIDEBAR                                       */
        /* ============================================= */
        .app-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            z-index: 1040;
            transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        /* Collapsed desktop state */
        .app-sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        .app-sidebar.collapsed .sidebar-brand-text,
        .app-sidebar.collapsed .sidebar-section-label,
        .app-sidebar.collapsed .sidebar-link-text,
        .app-sidebar.collapsed .sidebar-user-info,
        .app-sidebar.collapsed .sidebar-collapse-label {
            opacity: 0;
            width: 0;
            overflow: hidden;
            white-space: nowrap;
            transition: opacity 0.15s;
        }
        .app-sidebar.collapsed .sidebar-link {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }
        .app-sidebar.collapsed .sidebar-link i {
            margin-right: 0;
            font-size: 1.25rem;
        }
        .app-sidebar.collapsed .sidebar-section {
            padding-left: 0;
            padding-right: 0;
        }
        .app-sidebar.collapsed .sidebar-section-label {
            height: 0;
            margin: 0;
            padding: 0;
        }
        .app-sidebar.collapsed .sidebar-brand {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }
        .app-sidebar.collapsed .sidebar-user-block {
            justify-content: center;
            padding: 0.75rem 0.5rem;
        }
        .app-sidebar.collapsed .sidebar-toggle-btn {
            justify-content: center;
        }

        /* Brand / Logo */
        .sidebar-brand {
            display: flex;
            align-items: center;
            padding: 1.25rem 1.25rem;
            gap: 0.625rem;
            border-bottom: 1px solid var(--sidebar-border);
            min-height: 64px;
            flex-shrink: 0;
        }
        .sidebar-brand-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .sidebar-brand-text {
            font-weight: 700;
            font-size: 1.05rem;
            color: #f1f5f9;
            letter-spacing: -0.3px;
            white-space: nowrap;
            transition: opacity 0.15s;
        }

        /* Navigation area (scrollable) */
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0.5rem 0;
            scrollbar-width: thin;
            scrollbar-color: var(--sidebar-border) transparent;
        }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: var(--sidebar-border); border-radius: 4px; }

        /* Section group */
        .sidebar-section {
            padding: 0 0.75rem;
            margin-bottom: 0.25rem;
        }
        .sidebar-section-label {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--sidebar-section-text);
            padding: 0.75rem 0.625rem 0.375rem;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.15s;
        }

        /* Nav link */
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.625rem;
            margin: 1px 0;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.15s ease;
            white-space: nowrap;
            position: relative;
        }
        .sidebar-link i {
            font-size: 1.1rem;
            width: 1.5rem;
            text-align: center;
            margin-right: 0.625rem;
            flex-shrink: 0;
            transition: margin 0.15s;
        }
        .sidebar-link-text {
            transition: opacity 0.15s;
            white-space: nowrap;
        }
        .sidebar-link:hover {
            background: rgba(255,255,255,0.06);
            color: var(--sidebar-text-hover);
        }
        .sidebar-link.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
            font-weight: 600;
        }
        .sidebar-link.active::before {
            content: '';
            position: absolute;
            left: -0.75rem;
            top: 0.375rem;
            bottom: 0.375rem;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: #3b82f6;
        }

        /* Tooltip for collapsed state */
        .app-sidebar.collapsed .sidebar-link[data-bs-toggle="tooltip"] {
            overflow: visible;
        }

        /* Bottom user block */
        .sidebar-user-block {
            border-top: 1px solid var(--sidebar-border);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            flex-shrink: 0;
        }
        .sidebar-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sidebar-user-info {
            flex: 1;
            overflow: hidden;
            transition: opacity 0.15s;
        }
        .sidebar-user-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: #e2e8f0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-user-role {
            font-size: 0.7rem;
            color: var(--sidebar-text);
            white-space: nowrap;
        }

        /* Collapse toggle button */
        .sidebar-toggle-wrap {
            border-top: 1px solid var(--sidebar-border);
            padding: 0.5rem 0.75rem;
            flex-shrink: 0;
        }
        .sidebar-toggle-btn {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.5rem;
            width: 100%;
            border: none;
            background: transparent;
            color: var(--sidebar-text);
            font-size: 0.8rem;
            padding: 0.375rem 0.625rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .sidebar-toggle-btn:hover { background: rgba(255,255,255,0.06); }
        .sidebar-toggle-btn i {
            font-size: 1rem;
            transition: transform 0.25s;
        }
        .app-sidebar.collapsed .sidebar-toggle-btn i {
            transform: rotate(180deg);
        }

        /* Overlay for mobile drawer */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1039;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.show { display: block; }

        /* ============================================= */
        /* TOPBAR                                        */
        /* ============================================= */
        .app-topbar {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--topbar-height);
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            z-index: 1030;
            transition: left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-collapsed .app-topbar {
            left: var(--sidebar-collapsed-width);
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .topbar-hamburger {
            display: none;
            border: none;
            background: none;
            font-size: 1.25rem;
            color: #334155;
            padding: 0.25rem;
            cursor: pointer;
        }
        .topbar-page-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #0f172a;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .topbar-user-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            background: none;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            transition: background 0.15s;
        }
        .topbar-user-btn:hover { background: #f1f5f9; }
        .topbar-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            font-weight: 600;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ============================================= */
        /* MAIN CONTENT                                  */
        /* ============================================= */
        .app-content {
            margin-left: var(--sidebar-width);
            padding-top: var(--topbar-height);
            min-height: 100vh;
            transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-collapsed .app-content {
            margin-left: var(--sidebar-collapsed-width);
        }
        .app-content > .content-inner {
            padding: 1.5rem;
        }
        /* Override Bootstrap container max-width inside sidebar layout */
        .app-content .container {
            max-width: 100%;
            padding-left: 0;
            padding-right: 0;
        }

        /* ============================================= */
        /* SHARED COMPONENT STYLES                       */
        /* ============================================= */
        /* Cards & Containers */
        .card {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            background-color: #ffffff;
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.25rem 1.5rem;
            border-top-left-radius: 12px !important;
            border-top-right-radius: 12px !important;
        }
        .card-body { padding: 1.5rem; }

        /* Typography & Utilities */
        h1, h2, h3, h4, h5, h6 { color: #0f172a; font-weight: 600; }
        .text-muted { color: #64748b !important; }
        
        /* Buttons */
        .btn { border-radius: 8px; font-weight: 500; padding: 0.5rem 1rem; transition: all 0.2s; }
        .btn-primary { background-color: #2563eb; border-color: #2563eb; }
        .btn-primary:hover {
            background-color: #1d4ed8; border-color: #1d4ed8;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }
        .btn-outline-primary { color: #2563eb; border-color: #2563eb; }
        .btn-outline-primary:hover { background-color: #eff6ff; color: #1d4ed8; border-color: #2563eb; }

        /* Tables */
        .table { margin-bottom: 0; color: #334155; }
        .table th {
            font-weight: 600; color: #475569; text-transform: uppercase;
            font-size: 0.75rem; letter-spacing: 0.05em;
            padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; background-color: #f8fafc;
        }
        .table td { padding: 1rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .table-hover tbody tr:hover { background-color: #f8fafc; }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 8px; border: 1px solid #cbd5e1;
            padding: 0.6rem 1rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .form-control:focus, .form-select:focus {
            border-color: #93c5fd; box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.15);
        }
        .form-label { font-weight: 500; color: #475569; margin-bottom: 0.5rem; }

        /* Badges */
        .badge { font-weight: 500; padding: 0.35em 0.8em; border-radius: 6px; }

        /* ============================================= */
        /* RESPONSIVE                                    */
        /* ============================================= */

        /* Tablet: sidebar off-canvas, can be toggled */
        @media (max-width: 991.98px) {
            .app-sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width) !important;
            }
            .app-sidebar.mobile-open {
                transform: translateX(0);
            }
            .app-topbar {
                left: 0 !important;
            }
            .app-content {
                margin-left: 0 !important;
            }
            .topbar-hamburger { display: block; }

            /* Undo collapsed state on mobile */
            .app-sidebar .sidebar-brand-text,
            .app-sidebar .sidebar-section-label,
            .app-sidebar .sidebar-link-text,
            .app-sidebar .sidebar-user-info,
            .app-sidebar .sidebar-collapse-label {
                opacity: 1 !important; width: auto !important; overflow: visible !important;
            }
            .app-sidebar .sidebar-link { justify-content: flex-start !important; padding-left: 0.625rem !important; padding-right: 0.625rem !important; }
            .app-sidebar .sidebar-link i { margin-right: 0.625rem !important; font-size: 1.1rem !important; }
            .app-sidebar .sidebar-brand { justify-content: flex-start !important; padding-left: 1.25rem !important; }
            .app-sidebar .sidebar-user-block { justify-content: flex-start !important; padding: 0.75rem 1rem !important; }

            .sidebar-toggle-wrap { display: none; }
        }

        /* Mobile small */
        @media (max-width: 575.98px) {
            .app-content > .content-inner { padding: 1rem; }
            .topbar-page-title { display: none; }
        }
    </style>
</head>
<body class="{{ auth()->check() ? '' : 'no-sidebar' }}">

@auth
@php
    $currentUser = auth()->user();
    $authorization = app(\App\Services\AuthorizationService::class);
    $currentRoute = request()->route() ? request()->route()->getName() : '';
@endphp

{{-- ===================== SIDEBAR ===================== --}}
<aside class="app-sidebar" id="appSidebar">
    {{-- Brand --}}
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-journal-text"></i>
        </div>
        <span class="sidebar-brand-text">Learning Report</span>
    </div>

    {{-- Navigation --}}
    <nav class="sidebar-nav">

        {{-- ===== COACH ROLE ===== --}}
        @if($currentUser->role === 'coach')
            <div class="sidebar-section">
                <div class="sidebar-section-label">Laporan</div>
                <a href="{{ route('coach.reports.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'coach.reports.index') || str_starts_with($currentRoute, 'coach.reports.edit') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Laporan Saya">
                    <i class="bi bi-collection"></i>
                    <span class="sidebar-link-text">Laporan Saya</span>
                </a>
                <a href="{{ route('coach.reports.create') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'coach.reports.create') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Buat Laporan">
                    <i class="bi bi-plus-circle"></i>
                    <span class="sidebar-link-text">Buat Laporan</span>
                </a>
            </div>
            @if($authorization->allows($currentUser, 'students.view'))
            <div class="sidebar-section">
                <div class="sidebar-section-label">Kelas</div>
                <a href="{{ route('coach.students.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'coach.students') || str_starts_with($currentRoute, 'students.') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Siswa Kelas">
                    <i class="bi bi-people-fill"></i>
                    <span class="sidebar-link-text">Siswa Kelas</span>
                </a>
            </div>
            @endif

        {{-- ===== SUPERADMIN / RELATION ===== --}}
        @elseif(in_array($currentUser->role, ['relation', 'superadmin'], true))
            @if($currentUser->isSuperAdmin())
            <div class="sidebar-section">
                <a href="{{ route('admin.dashboard') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.dashboard') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span class="sidebar-link-text">Dashboard</span>
                </a>
            </div>
            @endif

            {{-- Operasional --}}
            @if($authorization->allows($currentUser, 'reports.view_all') || $authorization->allows($currentUser, 'attendance.view'))
            <div class="sidebar-section">
                <div class="sidebar-section-label">Operasional</div>
                @if($authorization->allows($currentUser, 'reports.view_all'))
                <a href="{{ route('admin.reports.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.reports') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Laporan Coach">
                    <i class="bi bi-file-earmark-check"></i>
                    <span class="sidebar-link-text">Laporan Coach</span>
                </a>
                @endif
                @if($authorization->allows($currentUser, 'attendance.view'))
                <a href="{{ route('attendance.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'attendance') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Kehadiran">
                    <i class="bi bi-calendar-check"></i>
                    <span class="sidebar-link-text">Kehadiran</span>
                </a>
                @endif
            </div>
            @endif

            {{-- Data Induk --}}
            @if($authorization->allows($currentUser, 'schools.view') || $authorization->allows($currentUser, 'program_classes.view') || $authorization->allows($currentUser, 'programs.view'))
            <div class="sidebar-section">
                <div class="sidebar-section-label">Master Data</div>
                @if($authorization->allows($currentUser, 'schools.view'))
                <a href="{{ route('admin.schools.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.schools') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Sekolah">
                    <i class="bi bi-building"></i>
                    <span class="sidebar-link-text">Sekolah</span>
                </a>
                @endif
                @if($authorization->allows($currentUser, 'program_classes.view'))
                <a href="{{ route('admin.classes.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.classes') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Kelas">
                    <i class="bi bi-easel"></i>
                    <span class="sidebar-link-text">Kelas</span>
                </a>
                @endif
                @if($authorization->allows($currentUser, 'programs.view'))
                <a href="{{ route('admin.programs.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.programs') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Program">
                    <i class="bi bi-book"></i>
                    <span class="sidebar-link-text">Program</span>
                </a>
                @endif
            </div>
            @endif

            {{-- Manajemen --}}
            @if($authorization->allows($currentUser, 'coaches.view') || $authorization->allows($currentUser, 'users.manage'))
            <div class="sidebar-section">
                <div class="sidebar-section-label">Manajemen</div>
                @if($authorization->allows($currentUser, 'coaches.view'))
                <a href="{{ route('admin.coaches.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.coaches') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Coach & Penugasan">
                    <i class="bi bi-person-badge"></i>
                    <span class="sidebar-link-text">Coach & Penugasan</span>
                </a>
                @endif
                @if($authorization->allows($currentUser, 'users.manage'))
                <a href="{{ route('admin.users.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.users') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Akun">
                    <i class="bi bi-people"></i>
                    <span class="sidebar-link-text">Manajemen Akun</span>
                </a>
                @endif
            </div>
            @endif

        {{-- ===== SPV COACH ===== --}}
        @elseif($currentUser->role === 'spv_coach')
            @if($authorization->allows($currentUser, 'dashboard.view'))
            <div class="sidebar-section">
                <a href="{{ route('admin.dashboard') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.dashboard') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span class="sidebar-link-text">Dashboard</span>
                </a>
            </div>
            @endif

            @if($authorization->allows($currentUser, 'coaches.view'))
            <div class="sidebar-section">
                <div class="sidebar-section-label">Tim Coach</div>
                <a href="{{ route('admin.coaches.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.coaches') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Coach & Penugasan">
                    <i class="bi bi-person-badge"></i>
                    <span class="sidebar-link-text">Coach & Penugasan</span>
                </a>
            </div>
            @endif

            @if($authorization->allows($currentUser, 'reports.view_all') || $authorization->allows($currentUser, 'attendance.view'))
            <div class="sidebar-section">
                <div class="sidebar-section-label">Operasional</div>
                @if($authorization->allows($currentUser, 'reports.view_all'))
                <a href="{{ route('admin.reports.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.reports') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Laporan Coach">
                    <i class="bi bi-file-earmark-check"></i>
                    <span class="sidebar-link-text">Laporan Coach</span>
                </a>
                @endif
                @if($authorization->allows($currentUser, 'attendance.view'))
                <a href="{{ route('attendance.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'attendance') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Kehadiran">
                    <i class="bi bi-calendar-check"></i>
                    <span class="sidebar-link-text">Kehadiran</span>
                </a>
                @endif
            </div>
            @endif

        {{-- ===== SCHOOL PIC ===== --}}
        @elseif($currentUser->role === 'school_pic')
            <div class="sidebar-section">
                <a href="{{ route('pic.dashboard') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'pic.dashboard') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span class="sidebar-link-text">Dashboard</span>
                </a>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-section-label">Operasional</div>
                @if($authorization->allows($currentUser, 'reports.view_all'))
                <a href="{{ route('admin.reports.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.reports') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Laporan Coach">
                    <i class="bi bi-file-earmark-check"></i>
                    <span class="sidebar-link-text">Laporan Coach</span>
                </a>
                @endif
                @if($authorization->allows($currentUser, 'attendance.view'))
                <a href="{{ route('attendance.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'attendance') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Kehadiran">
                    <i class="bi bi-calendar-check"></i>
                    <span class="sidebar-link-text">Kehadiran</span>
                </a>
                @endif
            </div>

        {{-- ===== TEACHER SCHOOL ===== --}}
        @elseif($currentUser->role === 'teacher_school')
            <div class="sidebar-section">
                <div class="sidebar-section-label">Operasional</div>
                @if($authorization->allows($currentUser, 'reports.view_all'))
                <a href="{{ route('admin.reports.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'admin.reports') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Laporan Coach">
                    <i class="bi bi-file-earmark-check"></i>
                    <span class="sidebar-link-text">Laporan Coach</span>
                </a>
                @endif
                @if($authorization->allows($currentUser, 'attendance.view'))
                <a href="{{ route('attendance.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'attendance') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Kehadiran">
                    <i class="bi bi-calendar-check"></i>
                    <span class="sidebar-link-text">Kehadiran</span>
                </a>
                @endif
            </div>

        {{-- ===== FINANCE ===== --}}
        @elseif($currentUser->role === 'finance')
            <div class="sidebar-section">
                <div class="sidebar-section-label">Data</div>
                @if($authorization->allows($currentUser, 'attendance.view'))
                <a href="{{ route('attendance.index') }}"
                   class="sidebar-link {{ str_starts_with($currentRoute, 'attendance') ? 'active' : '' }}"
                   data-bs-toggle="tooltip" data-bs-placement="right" title="Kehadiran & Export">
                    <i class="bi bi-calendar-check"></i>
                    <span class="sidebar-link-text">Kehadiran & Export</span>
                </a>
                @endif
            </div>
        @endif

    </nav>

    {{-- User block at bottom --}}
    <div class="sidebar-user-block">
        <div class="sidebar-user-avatar" data-bs-toggle="tooltip" data-bs-placement="right" title="{{ $currentUser->name }}">
            {{ substr($currentUser->name, 0, 1) }}
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name">{{ $currentUser->name }}</div>
            <div class="sidebar-user-role">{{ $currentUser->roleLabel() }}</div>
        </div>
    </div>

    {{-- Collapse toggle (desktop only) --}}
    <div class="sidebar-toggle-wrap">
        <button class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <i class="bi bi-chevron-left"></i>
            <span class="sidebar-collapse-label">Kecilkan Menu</span>
        </button>
    </div>
</aside>

{{-- Mobile overlay --}}
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

{{-- ===================== TOPBAR ===================== --}}
<header class="app-topbar">
    <div class="topbar-left">
        <button class="topbar-hamburger" onclick="openMobileSidebar()" aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>
        <span class="topbar-page-title">@yield('title', 'Learning Report System')</span>
    </div>
    <div class="topbar-right">
        <div class="dropdown">
            <button class="topbar-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="topbar-user-avatar">{{ substr($currentUser->name, 0, 1) }}</div>
                <span class="d-none d-md-inline fw-medium" style="font-size: 0.85rem; color: #334155;">{{ $currentUser->name }}</span>
                <i class="bi bi-chevron-down" style="font-size: 0.7rem; color: #64748b;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3 mt-2" style="min-width: 200px;">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-semibold" style="font-size: 0.85rem;">{{ $currentUser->name }}</div>
                    <div class="text-muted" style="font-size: 0.75rem;">{{ $currentUser->roleLabel() }}</div>
                </li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item text-danger py-2 d-flex align-items-center" type="submit">
                            <i class="bi bi-box-arrow-right me-2"></i> Keluar
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

{{-- ===================== CONTENT ===================== --}}
<div class="app-content">
    <div class="content-inner">
        {{-- Flash message sukses --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center py-3 mb-4" role="alert">
                <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
                <div>
                    <strong>Berhasil!</strong><br>
                    <span class="text-muted">{{ session('success') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Flash message error --}}
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center py-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-4 me-3"></i>
                <div>
                    <strong>Oops, terjadi kesalahan!</strong><br>
                    <span class="text-muted">{{ session('error') }}</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>
</div>

@else
{{-- ===================== GUEST (Login page) ===================== --}}
<main>
    @yield('content')
</main>
@endauth

{{-- Reusable Confirmation Modal --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="confirmModalLabel">Konfirmasi Tindakan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3 pb-4">
                <p id="confirmModalMessage" class="text-muted mb-0 fs-5">Apakah Anda yakin?</p>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light rounded-3 px-4 fw-medium" data-bs-dismiss="modal">Batal</button>
                <form id="confirmModalForm" method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="_method" id="confirmModalMethod" value="DELETE">
                    <button type="submit" id="confirmModalSubmitBtn" class="btn btn-danger rounded-3 px-4 fw-medium">Ya, Lanjutkan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Global Confirmation Dialog Helper
    function confirmAction(url, message = 'Apakah Anda yakin ingin melakukan tindakan ini?', method = 'DELETE', buttonClass = 'btn-danger', buttonText = 'Ya, Lanjutkan') {
        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        document.getElementById('confirmModalMessage').textContent = message;
        document.getElementById('confirmModalForm').action = url;
        document.getElementById('confirmModalMethod').value = method;
        
        const submitBtn = document.getElementById('confirmModalSubmitBtn');
        submitBtn.className = `btn ${buttonClass} rounded-3 px-4 fw-medium`;
        submitBtn.textContent = buttonText;
        
        modal.show();
    }

    // ======== Sidebar Logic ========
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const COLLAPSED_KEY = 'lrs_sidebar_collapsed';

    // Restore collapsed state from localStorage (desktop only)
    if (window.innerWidth >= 992 && localStorage.getItem(COLLAPSED_KEY) === '1') {
        sidebar?.classList.add('collapsed');
        document.body.classList.add('sidebar-collapsed');
    }

    function toggleSidebar() {
        if (!sidebar) return;
        sidebar.classList.toggle('collapsed');
        document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem(COLLAPSED_KEY, sidebar.classList.contains('collapsed') ? '1' : '0');

        // Bootstrap tooltips: enable when collapsed, destroy when expanded
        initTooltips();
    }

    function openMobileSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('mobile-open');
        overlay?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('mobile-open');
        overlay?.classList.remove('show');
        document.body.style.overflow = '';
    }

    function initTooltips() {
        // Destroy existing tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            const tt = bootstrap.Tooltip.getInstance(el);
            if (tt) tt.dispose();
        });

        // Only enable tooltips when sidebar is collapsed AND on desktop
        if (sidebar?.classList.contains('collapsed') && window.innerWidth >= 992) {
            document.querySelectorAll('.sidebar-link[data-bs-toggle="tooltip"], .sidebar-user-avatar[data-bs-toggle="tooltip"]').forEach(el => {
                new bootstrap.Tooltip(el, { trigger: 'hover', delay: { show: 300, hide: 0 } });
            });
        }
    }

    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            closeMobileSidebar();
        }
        initTooltips();
    });

    // Close mobile sidebar when ESC pressed
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeMobileSidebar();
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize tooltips after Bootstrap is loaded
    document.addEventListener('DOMContentLoaded', () => initTooltips());
</script>
@yield('scripts') {{-- untuk JS tambahan per halaman --}}
</body>
</html>
