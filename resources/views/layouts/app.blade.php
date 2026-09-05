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
            /* Premium Color Palette */
            --primary: #4f46e5;       /* Indigo 600 */
            --primary-hover: #4338ca; /* Indigo 700 */
            --primary-light: #e0e7ff; /* Indigo 100 */
            --primary-alpha: rgba(79, 70, 229, 0.1);
            
            --secondary: #64748b;     /* Slate 500 */
            --secondary-bg: #f8fafc;  /* Slate 50 */
            
            --success: #059669;       /* Emerald 600 */
            --success-light: #d1fae5; /* Emerald 100 */
            
            --warning: #d97706;       /* Amber 600 */
            --warning-light: #fef3c7; /* Amber 100 */
            
            --danger: #dc2626;        /* Red 600 */
            --danger-light: #fee2e2;  /* Red 100 */
            
            /* Text Colors */
            --text-main: #0f172a;     /* Slate 900 */
            --text-muted: #475569;    /* Slate 600 */
            --text-light: #94a3b8;    /* Slate 400 */
            
            /* Backgrounds & Borders */
            --bg-body: #f4f7f9;
            --bg-surface: #ffffff;
            --border-color: #e2e8f0;  /* Slate 200 */
            --border-focus: #a5b4fc;  /* Indigo 300 */
            
            /* Sidebar (Sleek Dark Mode) */
            --sidebar-width: 270px;
            --sidebar-collapsed-width: 80px;
            --sidebar-bg: #0b1121;
            --sidebar-text: #94a3b8;
            --sidebar-text-hover: #f8fafc;
            --sidebar-active-bg: rgba(255, 255, 255, 0.08);
            --sidebar-active-text: #ffffff;
            --sidebar-section-text: #64748b;
            --sidebar-border: #1e293b;
            
            /* Layout */
            --topbar-height: 72px; 
            
            /* Shadows & Radius (Gestalt: Figure-Ground) */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            --shadow-glow: 0 0 15px rgba(79, 70, 229, 0.3);
            
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 24px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.6;
        }

        /* ============================================= */
        /* SIDEBAR (Law of Common Region)                */
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
            transition: width 0.3s cubic-bezier(0.2, 0, 0, 1), transform 0.3s cubic-bezier(0.2, 0, 0, 1);
            overflow: hidden;
            box-shadow: 4px 0 24px rgba(0,0,0,0.05);
        }

        /* Collapsed desktop state */
        .app-sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .app-sidebar.collapsed .sidebar-brand-text,
        .app-sidebar.collapsed .sidebar-section-label,
        .app-sidebar.collapsed .sidebar-link-text,
        .app-sidebar.collapsed .sidebar-user-info,
        .app-sidebar.collapsed .sidebar-collapse-label {
            opacity: 0; width: 0; overflow: hidden; white-space: nowrap; transition: opacity 0.2s;
        }
        .app-sidebar.collapsed .sidebar-link { justify-content: center; padding: 0.75rem 0; }
        .app-sidebar.collapsed .sidebar-link i { margin-right: 0; font-size: 1.3rem; }
        .app-sidebar.collapsed .sidebar-brand { justify-content: center; padding: 1.25rem 0; }
        .app-sidebar.collapsed .sidebar-user-block { justify-content: center; padding: 1rem 0; }
        .app-sidebar.collapsed .sidebar-toggle-btn { justify-content: center; }

        /* Brand / Logo */
        .sidebar-brand {
            display: flex; align-items: center; padding: 1.5rem; gap: 0.75rem;
            min-height: var(--topbar-height); flex-shrink: 0;
            background: rgba(255,255,255,0.02);
        }
        .sidebar-brand-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), #818cf8);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.1rem; flex-shrink: 0;
            box-shadow: var(--shadow-glow);
        }
        .sidebar-brand-text {
            font-weight: 700; font-size: 1.15rem; color: #fff;
            letter-spacing: -0.02em; white-space: nowrap; transition: opacity 0.2s;
        }

        /* Navigation area */
        .sidebar-nav {
            flex: 1; overflow-y: auto; overflow-x: hidden; padding: 1rem 0;
            scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent;
        }
        .sidebar-nav::-webkit-scrollbar { width: 5px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

        /* Section group (Law of Proximity) */
        .sidebar-section { padding: 0 1rem; margin-bottom: 1.5rem; }
        .sidebar-section-label {
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.1em; color: var(--sidebar-section-text);
            padding: 0 0.75rem 0.5rem; white-space: nowrap; overflow: hidden; transition: opacity 0.2s;
        }

        /* Nav link (Law of Similarity) */
        .sidebar-link {
            display: flex; align-items: center; padding: 0.65rem 0.85rem;
            margin: 0.25rem 0; color: var(--sidebar-text); text-decoration: none;
            border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 500;
            transition: all 0.2s cubic-bezier(0.2, 0, 0, 1); white-space: nowrap; position: relative;
        }
        .sidebar-link i {
            font-size: 1.15rem; width: 1.75rem; text-align: center;
            margin-right: 0.75rem; flex-shrink: 0; transition: margin 0.2s, transform 0.2s;
        }
        .sidebar-link-text { transition: opacity 0.2s; white-space: nowrap; }
        
        .sidebar-link:hover {
            background: rgba(255,255,255,0.04); color: var(--sidebar-text-hover);
            transform: translateX(3px);
        }
        .sidebar-link.active {
            background: var(--sidebar-active-bg); color: var(--sidebar-active-text); font-weight: 600;
        }
        .sidebar-link.active i { color: #818cf8; }
        
        /* Tooltip for collapsed state */
        .app-sidebar.collapsed .sidebar-link[data-bs-toggle="tooltip"] { overflow: visible; }

        /* Bottom user block */
        .sidebar-user-block {
            padding: 1.25rem 1rem; display: flex; align-items: center; gap: 0.75rem;
            flex-shrink: 0; background: rgba(0,0,0,0.15);
        }
        .sidebar-user-avatar {
            width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, var(--secondary), #334155);
            color: #fff; font-weight: 600; font-size: 0.95rem;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .sidebar-user-info { flex: 1; overflow: hidden; transition: opacity 0.2s; }
        .sidebar-user-name {
            font-size: 0.85rem; font-weight: 600; color: #fff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;
        }
        .sidebar-user-role { font-size: 0.75rem; color: var(--sidebar-text); white-space: nowrap; margin-top: 0.25rem; }

        /* Collapse toggle button */
        .sidebar-toggle-wrap { padding: 0.75rem 1rem; flex-shrink: 0; background: rgba(0,0,0,0.25); }
        .sidebar-toggle-btn {
            display: flex; align-items: center; justify-content: flex-start; gap: 0.5rem;
            width: 100%; border: none; background: transparent; color: var(--sidebar-text);
            font-size: 0.85rem; font-weight: 500; padding: 0.5rem; border-radius: var(--radius-sm);
            cursor: pointer; transition: all 0.2s;
        }
        .sidebar-toggle-btn:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-toggle-btn i { font-size: 1rem; transition: transform 0.3s; }
        .app-sidebar.collapsed .sidebar-toggle-btn i { transform: rotate(180deg); }

        /* Overlay for mobile drawer */
        .sidebar-overlay {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4);
            z-index: 1039; backdrop-filter: blur(4px); transition: opacity 0.3s;
        }
        .sidebar-overlay.show { display: block; }

        /* ============================================= */
        /* TOPBAR                                        */
        /* ============================================= */
        .app-topbar {
            position: fixed; top: 0; right: 0; left: var(--sidebar-width);
            height: var(--topbar-height); background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem; z-index: 1030; transition: left 0.3s cubic-bezier(0.2, 0, 0, 1);
        }
        .sidebar-collapsed .app-topbar { left: var(--sidebar-collapsed-width); }
        
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-hamburger {
            display: none; border: none; background: none; font-size: 1.5rem;
            color: var(--text-main); padding: 0.25rem; cursor: pointer; transition: color 0.2s;
        }
        .topbar-hamburger:hover { color: var(--primary); }
        .topbar-page-title { font-size: 1.1rem; font-weight: 600; color: var(--text-main); letter-spacing: -0.01em; }
        
        .topbar-right { display: flex; align-items: center; gap: 1.25rem; }
        
        .topbar-user-btn {
            display: flex; align-items: center; gap: 0.75rem; border: none; background: transparent;
            cursor: pointer; padding: 0.35rem 0.5rem; border-radius: var(--radius-md); transition: all 0.2s;
        }
        .topbar-user-btn:hover { background: var(--secondary-bg); }
        .topbar-user-avatar {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), #818cf8);
            color: #fff; font-weight: 600; font-size: 0.9rem;
            display: flex; align-items: center; justify-content: center;
        }

        /* ============================================= */
        /* MAIN CONTENT                                  */
        /* ============================================= */
        .app-content {
            margin-left: var(--sidebar-width); padding-top: var(--topbar-height);
            min-height: 100vh; transition: margin-left 0.3s cubic-bezier(0.2, 0, 0, 1);
        }
        .sidebar-collapsed .app-content { margin-left: var(--sidebar-collapsed-width); }
        .app-content > .content-inner { padding: 2rem; max-width: 1400px; margin: 0 auto; }
        .app-content .container { max-width: 100%; padding: 0; }

        /* ============================================= */
        /* SHARED COMPONENT STYLES                       */
        /* ============================================= */
        
        /* Cards (Gestalt: Figure-Ground) */
        .card {
            border-radius: var(--radius-lg); border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: var(--shadow-sm); background-color: var(--bg-surface);
            margin-bottom: 1.5rem; transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover { box-shadow: var(--shadow-md); }
        .card-header {
            background-color: transparent; border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem; font-weight: 600;
        }
        .card-body { padding: 1.5rem; }

        /* Typography & Utilities */
        h1, h2, h3, h4, h5, h6 { color: var(--text-main); font-weight: 600; letter-spacing: -0.02em; }
        .text-muted { color: var(--text-muted) !important; }
        
        /* Buttons (Gestalt: Continuity & Proximity) */
        .btn { 
            border-radius: var(--radius-md); font-weight: 500; padding: 0.6rem 1.25rem; 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex;
            align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.95rem;
        }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.85rem; border-radius: var(--radius-sm); }
        .btn-lg { padding: 0.8rem 1.5rem; font-size: 1.05rem; border-radius: var(--radius-lg); }
        
        .btn-primary { background-color: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2); }
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--primary-hover); border-color: var(--primary-hover);
            transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); color: #fff;
        }
        .btn-primary:active { transform: translateY(0); }
        
        .btn-outline-primary { color: var(--primary); border-color: var(--primary); background: transparent; }
        .btn-outline-primary:hover, .btn-outline-primary:focus { 
            background-color: var(--primary); color: #fff; 
        }
        
        .btn-light { background-color: #f8fafc; border-color: #e2e8f0; color: var(--text-main); }
        .btn-light:hover { background-color: #f1f5f9; border-color: #cbd5e1; }
        
        .btn-danger { background-color: var(--danger); border-color: var(--danger); color: #fff; }
        .btn-danger:hover { background-color: #b91c1c; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); transform: translateY(-1px); }

        /* Tables (Gestalt: Common Region) */
        .table-responsive {
            border-radius: var(--radius-md); border: 1px solid var(--border-color);
            background: var(--bg-surface); overflow-x: auto; -webkit-overflow-scrolling: touch;
        }
        .card > .table-responsive { border: none; border-radius: 0; margin: 0; }
        .card > .table-responsive:first-child { border-top-left-radius: var(--radius-lg); border-top-right-radius: var(--radius-lg); }
        .card > .table-responsive:last-child { border-bottom-left-radius: var(--radius-lg); border-bottom-right-radius: var(--radius-lg); }
        
        .table { margin-bottom: 0; color: var(--text-main); width: 100%; }
        .table th {
            font-weight: 600; color: var(--text-muted); text-transform: uppercase;
            font-size: 0.75rem; letter-spacing: 0.05em; padding: 1.25rem 1.5rem; 
            border-bottom: 1px solid var(--border-color); background-color: #f8fafc; white-space: nowrap;
        }
        .table td { padding: 1.25rem 1.5rem; vertical-align: middle; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; }
        .table tbody tr:last-child td { border-bottom: none; }
        .table-hover tbody tr:hover { background-color: #f8fafc; }
        
        /* Forms (Gestalt: Closure) */
        .form-control, .form-select {
            border-radius: var(--radius-md); border: 1px solid #cbd5e1;
            padding: 0.7rem 1rem; box-shadow: var(--shadow-xs);
            font-size: 0.95rem; color: var(--text-main);
            transition: all 0.2s; background-color: #fff;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-alpha);
            outline: none; background-color: #fff;
        }
        .form-control::placeholder { color: var(--text-light); }
        .form-label { font-weight: 500; color: var(--text-main); margin-bottom: 0.5rem; font-size: 0.9rem; }
        
        /* Badges */
        .badge { 
            font-weight: 500; padding: 0.4em 0.8em; border-radius: 8px; 
            font-size: 0.75rem; letter-spacing: 0.02em; 
        }

        /* Alerts */
        .alert {
            border-radius: var(--radius-md); border: none; padding: 1rem 1.25rem;
            box-shadow: var(--shadow-sm); display: flex; align-items: flex-start;
        }
        
        /* Pagination */
        .pagination { margin-bottom: 0; gap: 0.25rem; }
        .page-link { 
            border: 1px solid var(--border-color); color: var(--text-muted); 
            padding: 0.5rem 0.85rem; border-radius: var(--radius-sm) !important; 
            font-weight: 500; transition: all 0.2s;
        }
        .page-link:hover { background-color: #f1f5f9; color: var(--text-main); }
        .page-item.active .page-link { 
            background-color: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 2px 4px var(--primary-alpha);
        }

        /* Modals */
        .modal-content {
            border: none; border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg); overflow: hidden;
        }
        .modal-header { border-bottom: 1px solid var(--border-color); padding: 1.5rem; background: #f8fafc; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { border-top: 1px solid var(--border-color); padding: 1.5rem; background: #f8fafc; }

        /* Responsive Improvements */
        @media (max-width: 767.98px) {
            .app-content > .content-inner { padding: 1.25rem; }
            .card-body, .card-header { padding: 1.25rem 1rem; }
            .modal-body, .modal-header, .modal-footer { padding: 1.25rem; }
            .btn-mobile-block { width: 100%; margin-bottom: 0.5rem; }
        }

        /* ============================================= */
        /* RESPONSIVE APP SHELL                          */
        /* ============================================= */

        /* Tablet: sidebar off-canvas */
        @media (max-width: 991.98px) {
            .app-sidebar { transform: translateX(-100%); width: var(--sidebar-width) !important; }
            .app-sidebar.mobile-open { transform: translateX(0); }
            .app-topbar { left: 0 !important; padding: 0 1rem; }
            .app-content { margin-left: 0 !important; }
            .topbar-hamburger { display: block; }

            /* Undo collapsed state on mobile */
            .app-sidebar .sidebar-brand-text, .app-sidebar .sidebar-section-label,
            .app-sidebar .sidebar-link-text, .app-sidebar .sidebar-user-info,
            .app-sidebar .sidebar-collapse-label {
                opacity: 1 !important; width: auto !important; overflow: visible !important;
            }
            .app-sidebar .sidebar-link { justify-content: flex-start !important; padding-left: 1rem !important; }
            .app-sidebar .sidebar-link i { margin-right: 1rem !important; }
            .app-sidebar .sidebar-brand { justify-content: flex-start !important; padding: 1.5rem 1rem !important; }
            .app-sidebar .sidebar-user-block { justify-content: flex-start !important; padding: 1.25rem 1rem !important; }
            .sidebar-toggle-wrap { display: none; }
        }

        /* Mobile small */
        @media (max-width: 575.98px) {
            .app-content > .content-inner { padding: 1rem; }
            .topbar-page-title { font-size: 1rem; }
            .topbar-right { gap: 0.5rem; }
        }

        /* ============================================= */
        /* MOBILE-FIRST POLISH                           */
        /* ============================================= */
        :root { --touch-target: 44px; }

        html { min-width: 320px; }
        img, video, iframe { max-width: 100%; }
        button, a, input, select, textarea { -webkit-tap-highlight-color: transparent; }
        :focus-visible { outline: 3px solid rgba(79, 70, 229, .35); outline-offset: 2px; }

        .topbar-left { flex: 1; }
        .topbar-right { flex: 0 0 auto; }
        .topbar-left, .topbar-right, .topbar-page-title,
        .content-inner, .content-inner .container, .content-inner .container-fluid,
        .row > [class*="col-"] { min-width: 0; }

        .topbar-page-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .app-content > .content-inner { width: 100%; }
        .container, .container-fluid { width: 100%; }

        /* Decorative statistic icons must never compete with the readable copy. */
        .card-body.position-relative > .position-absolute { z-index: 0; pointer-events: none; }
        .card-body.position-relative > div:not(.position-absolute) { position: relative; z-index: 1; max-width: 72%; }

        .btn { min-height: var(--touch-target); }
        .btn-sm { min-height: 38px; }
        .btn-close { min-width: var(--touch-target); min-height: var(--touch-target); padding: .75rem; }
        .form-control, .form-select { min-height: 48px; }
        textarea.form-control { min-height: 104px; }
        .form-check-input { width: 1.25em; height: 1.25em; }
        .form-text, .invalid-feedback { line-height: 1.45; }

        .alert { min-width: 0; }
        .alert > :not(.btn-close) { min-width: 0; }
        .alert .btn-close { flex: 0 0 auto; }

        /* All list/detail toolbars wrap before they can collide. */
        .content-inner > .container > .d-flex.justify-content-between.align-items-center,
        .content-inner > .container-fluid > .d-flex.justify-content-between.align-items-center,
        .content-inner > .d-flex.justify-content-between.align-items-center {
            gap: 1rem;
        }

        .table-responsive {
            width: 100%; max-width: 100%;
            overscroll-behavior-x: contain;
            scrollbar-width: thin;
        }
        .table-responsive::after {
            content: 'Geser untuk melihat kolom lainnya';
            display: none;
        }
        /* Dense tables keep their information hierarchy and scroll as one unit. */
        .table-responsive > .table { min-width: 0; }
        .table-responsive > .table:has(tr > :nth-child(4)) { min-width: 640px; }
        .table-responsive .table th,
        .table-responsive .table td { white-space: nowrap; }
        .table-responsive .table td:first-child,
        .table-responsive .table th:first-child { white-space: normal; }
        .table-responsive .table td { overflow-wrap: anywhere; }

        .modal-dialog { width: auto; max-width: calc(100% - 1.5rem); margin: .75rem auto; }
        .modal-content { max-height: calc(100dvh - 1.5rem); }
        .modal-body { overflow-y: auto; -webkit-overflow-scrolling: touch; }
        .modal-header, .modal-footer { flex-shrink: 0; }
        .modal-footer { gap: .5rem; }
        .modal-footer > * { margin: 0 !important; }

        @media (max-width: 991.98px) {
            .app-sidebar {
                width: min(var(--sidebar-width), calc(100vw - 1.5rem)) !important;
                box-shadow: 12px 0 32px rgba(15, 23, 42, .22);
            }
            .sidebar-brand { padding-top: max(1rem, env(safe-area-inset-top)); }
            .sidebar-nav { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
            .sidebar-brand { position: relative; }
            .sidebar-mobile-close {
                display: inline-flex; align-items: center; justify-content: center;
                margin-left: auto; width: var(--touch-target); height: var(--touch-target);
                border: 0; border-radius: var(--radius-md); background: transparent;
                color: var(--sidebar-text); font-size: 1.25rem;
            }
            .sidebar-mobile-close:hover, .sidebar-mobile-close:focus-visible {
                color: #fff; background: rgba(255,255,255,.08);
            }
            .app-topbar {
                height: calc(var(--topbar-height) + env(safe-area-inset-top));
                padding-top: env(safe-area-inset-top);
            }
            .app-content { padding-top: calc(var(--topbar-height) + env(safe-area-inset-top)); }
        }

        @media (max-width: 767.98px) {
            body { line-height: 1.5; }
            .app-content > .content-inner { padding: .875rem; }
            .card { margin-bottom: 1rem; border-radius: var(--radius-md); }
            .card-header, .card-body { padding: 1rem; }
            .card-header.d-flex, .card-body.d-flex { flex-wrap: wrap; gap: .75rem; }
            .card-header.d-flex > *, .card-body.d-flex > * { min-width: 0; }

            .content-inner > .container > .d-flex.justify-content-between.align-items-center,
            .content-inner > .container-fluid > .d-flex.justify-content-between.align-items-center,
            .content-inner > .d-flex.justify-content-between.align-items-center {
                align-items: stretch !important; flex-direction: column; margin-bottom: 1.25rem !important;
            }
            .content-inner > .container > .d-flex.justify-content-between.align-items-center > *,
            .content-inner > .container-fluid > .d-flex.justify-content-between.align-items-center > *,
            .content-inner > .d-flex.justify-content-between.align-items-center > * { width: 100%; }
            .content-inner > .container > .d-flex.justify-content-between.align-items-center > .badge,
            .content-inner > .container-fluid > .d-flex.justify-content-between.align-items-center > .badge { align-self: flex-start; width: auto; }

            .btn { white-space: nowrap; }
            .table-responsive { border-radius: var(--radius-sm); }
            .table-responsive > .table:has(tr > :nth-child(4)) { min-width: 620px; }
            .form-control, .form-select { font-size: 16px; }
            .table-responsive .table th { padding: .8rem .75rem; font-size: .7rem; }
            .table-responsive .table td { padding: .85rem .75rem; font-size: .875rem; }
            .table-responsive .table td .btn-sm { min-height: 38px; }

            .input-group > .form-control, .input-group > .form-select { min-width: 0; }
            .input-group .btn { flex: 0 0 auto; }
            .pagination { flex-wrap: wrap; }
            .page-link { min-width: 40px; min-height: 40px; display: inline-flex; align-items: center; justify-content: center; }

            .modal-dialog { max-width: calc(100% - 1rem); margin: .5rem auto; }
            .modal-content { max-height: calc(100dvh - 1rem); border-radius: var(--radius-lg); }
            .modal-header, .modal-body, .modal-footer { padding: 1rem; }
            .modal-footer { flex-direction: column-reverse; align-items: stretch; }
            .modal-footer .btn, .modal-footer form, .modal-footer form .btn { width: 100%; }

            .action-bar { flex-direction: column; align-items: stretch; gap: .625rem !important; margin-top: 1.5rem !important; }
            .action-bar .btn { width: 100%; }
            .row.g-4, .row.g-3 { --bs-gutter-y: 1rem; }
            .position-sticky { position: static !important; }
        }

        @media (max-width: 359.98px) {
            .app-content > .content-inner { padding: .75rem; }
            .topbar-page-title { font-size: .92rem; }
            .topbar-hamburger { padding-inline: .125rem; }
            .table-responsive > .table:has(tr > :nth-child(4)) { min-width: 580px; }
        }

        @media (min-width: 768px) {
            .sidebar-mobile-close { display: none; }
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
        <button type="button" class="sidebar-mobile-close" onclick="closeMobileSidebar()" aria-label="Tutup menu">
            <i class="bi bi-x-lg"></i>
        </button>
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
        <button class="topbar-hamburger" onclick="openMobileSidebar()" aria-label="Buka menu" aria-controls="appSidebar" aria-expanded="false">
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
        document.querySelector('.topbar-hamburger')?.setAttribute('aria-expanded', 'true');
    }

    function closeMobileSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('mobile-open');
        overlay?.classList.remove('show');
        document.body.style.overflow = '';
        document.querySelector('.topbar-hamburger')?.setAttribute('aria-expanded', 'false');
    }

    // A menu selection should return the user to the page immediately on mobile.
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) closeMobileSidebar();
        });
    });

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
