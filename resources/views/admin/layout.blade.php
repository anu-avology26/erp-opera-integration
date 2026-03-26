<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') – {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1e293b;
            --sidebar-text: #cbd5e1;
            --sidebar-active: #0d9488;
            --sidebar-active-bg: rgba(13, 148, 136, 0.2);
            --sidebar-active-text: #fff;
            --sidebar-hover: rgba(255,255,255,.06);
            --sidebar-group: #94a3b8;
            --sidebar-border: rgba(255,255,255,.06);
            --page-bg: #f1f5f9;
            --card-bg: #ffffff;
            --card-shadow: 0 1px 3px rgba(0,0,0,.05);
            --card-border: #e2e8f0;
            --header-bg: #fff;
            --header-shadow: 0 1px 2px rgba(0,0,0,.05);
            --text-primary: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #059669;
            --success-bg: #d1fae5;
            --success-light: #ecfdf5;
            --warning: #d97706;
            --warning-bg: #fef3c7;
            --warning-light: #fffbeb;
            --critical: #dc2626;
            --critical-bg: #fee2e2;
            --critical-light: #fef2f2;
            --unknown: #64748b;
            --unknown-bg: #f1f5f9;
            --primary: #0d9488;
            --primary-light: #ccfbf1;
            --accent: #14b8a6;
            --info: #0ea5e9;
            --info-bg: #e0f2fe;
            --info-light: #f0f9ff;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; background: var(--page-bg); color: var(--text-primary); }
        .admin-wrap { display: flex; min-height: 100vh; }
        .admin-wrap { --sidebar-width: 260px; --header-row-height: 64px; --navbar-height: var(--header-row-height); }
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 0;
            border-right: 1px solid var(--sidebar-border);
            overflow-y: auto;
            z-index: 40;
        }
        .admin-sidebar .brand {
            display: flex;
            align-items: center;
            min-height: var(--header-row-height, 64px);
            padding: 0 1.5rem;
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            border-bottom: 1px solid var(--sidebar-border);
            margin: 0;
            letter-spacing: -0.02em;
        }
        .admin-sidebar .brand span { color: var(--primary); }
        .admin-sidebar .nav-group {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--sidebar-group);
            padding: 1.25rem 1.5rem 0.5rem;
        }
        .admin-sidebar a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 1.5rem;
            margin: 0 0.75rem;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.9rem;
            border-radius: 0.5rem;
            transition: background .15s, color .15s;
        }
        .admin-sidebar a:hover { background: var(--sidebar-hover); color: #fff; }
        .admin-sidebar a.active { background: var(--sidebar-active-bg); color: var(--sidebar-active-text); font-weight: 600; }
        .admin-sidebar .nav-icon { flex-shrink: 0; width: 1.2rem; height: 1.2rem; opacity: 0.85; }
        .admin-sidebar .sidebar-footer { border-top: 1px solid var(--sidebar-border); }
        .admin-sidebar .sidebar-footer a,
        .admin-sidebar .sidebar-footer button { margin: 0 0.75rem; border-radius: 0.5rem; }
        .admin-sidebar .sidebar-footer button { display: flex; align-items: center; gap: 0.6rem; }
        .admin-main {
            flex: 1;
            margin-left: var(--sidebar-width, 240px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .admin-main .admin-navbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width, 260px);
            right: 0;
            height: var(--navbar-height, 56px);
            flex-shrink: 0;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--header-shadow);
            z-index: 30;
            
        }
        .admin-main-inner {
            flex: 1;
            overflow-y: auto;
            overflow-x: auto;
            padding: 1.5rem 2rem 2rem;
            padding-top: calc(var(--navbar-height, 56px) + 1rem);
        }
        .admin-main-footer {
            margin-top: 1rem;
            padding: 0.85rem 0 0.15rem;
            border-top: 1px solid var(--border);
            color: var(--text-muted);
            font-size: 0.82rem;
            text-align: center;
        }
        .page-header { margin-bottom: 1.75rem; }
        .page-title { font-size: 1.375rem; font-weight: 600; margin: 0 0 0.25rem 0; color: var(--text-primary); letter-spacing: -0.02em; }
        .page-subtitle { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
        .card {
            background: var(--card-bg);
            border-radius: 0.625rem;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--card-border);
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease, border-color 0.25s ease, background 0.25s ease;
        }
        .card:hover { transform: translateY(-4px); }
        .card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,.12);
            border-color: rgba(13, 148, 136, 0.55);
        }
        .card-title { font-size: 1.0625rem; font-weight: 600; margin: 0 0 1rem 0; color: var(--text-primary); }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        th, td { text-align: left; padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border); }
        th { font-weight: 600; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: .05em; }
        tr:last-child td { border-bottom: none; }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: #fff;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            font-family: inherit;
            text-decoration: none;
            font-weight: 500;
            transition: opacity .15s, background .15s;
        }
        .btn:hover { opacity: .95; }
        .btn-secondary { background: var(--text-muted); }
        .btn-success { background: var(--success); }
        .message { padding: 0.75rem 1.25rem; background: var(--success-bg); color: var(--success); border-radius: 0.5rem; margin-bottom: 1.25rem; border: 1px solid rgba(5,150,105,.15); }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; }
        .badge-healthy { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-critical { background: var(--critical-bg); color: var(--critical); }
        .badge-unknown { background: var(--unknown-bg); color: var(--unknown); }
        .mono { font-family: ui-monospace, monospace; font-size: 0.8rem; }
        .text-muted { color: var(--text-muted); font-size: 0.875rem; }
        .admin-main a:not(.btn) { color: var(--primary); text-decoration: none; }
        .admin-main a:not(.btn):hover { text-decoration: underline; }
        .grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .pagination {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .pagination-inner {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .page-btn,
        .page-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.35rem 0.6rem;
            border-radius: 0.45rem;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text-primary);
            text-decoration: none;
            min-width: 2rem;
        }
        .page-btn:hover,
        .page-num:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .page-num.is-active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            font-weight: 600;
        }
        .page-btn.is-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .page-ellipsis {
            padding: 0 0.25rem;
            color: var(--text-muted);
        }
        .stat-card {
            background: var(--card-bg);
            border-radius: 0.625rem;
            padding: 1.35rem 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--card-border);
            border-left: 4px solid var(--primary);
        }
        .stat-card.stat-success { border-left-color: var(--success); }
        .stat-card.stat-warning { border-left-color: var(--warning); }
        .stat-card.stat-critical { border-left-color: var(--critical); }
        .stat-card .value { font-size: 1.75rem; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
        .stat-card .label { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; }
        .chart-container { position: relative; height: 280px; margin-bottom: 0; }
        .errors-pre { font-size: 0.8rem; color: var(--critical); white-space: pre-wrap; margin: 0.5rem 0 0 0; }
        details { margin-top: 0.5rem; }
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.35rem; color: var(--text-primary); }
        .form-input { width: 100%; max-width: 28rem; padding: 0.5rem 0.75rem; border: 1px solid var(--border); border-radius: 0.375rem; font-size: 0.9375rem; font-family: inherit; }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-light); }
        .admin-sidebar .sidebar-footer { margin-top: auto; padding: 1rem 0; border-top: 1px solid var(--sidebar-border); }
        .admin-sidebar .sidebar-footer a { font-size: 0.9rem; }
        .admin-sidebar .sidebar-footer button { transition: background .15s, color .15s; }
        .admin-sidebar .sidebar-footer button:hover { background: var(--sidebar-hover); color: #fff; }
        /* Header - fixed top bar (does not scroll) */
        .admin-navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            min-height: var(--navbar-height, 56px);
            flex-shrink: 0;
        }
        .admin-navbar .navbar-datetime {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-variant-numeric: tabular-nums;
        }
        .admin-navbar .navbar-user {
            position: relative;
            z-index: 35;
        }
        .admin-navbar .navbar-user-trigger {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: var(--page-bg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            font-family: inherit;
        }
        .admin-navbar .navbar-user-trigger:hover {
            background: var(--sidebar-hover);
            border-color: var(--border);
            color: var(--primary);
        }
        .admin-navbar .navbar-user-trigger svg {
            width: 1.1rem;
            height: 1.1rem;
            opacity: 0.8;
        }
        .admin-navbar .navbar-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.35rem;
            min-width: 11rem;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,.1);
            z-index: 100;
            display: none;
            padding: 0.35rem 0;
        }
        .admin-navbar .navbar-dropdown.is-open { display: block; }
        .admin-navbar .navbar-dropdown a,
        .admin-navbar .navbar-dropdown button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.55rem 1rem;
            font-size: 0.875rem;
            color: var(--text-primary);
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            text-align: left;
            transition: background .15s, color .15s;
        }
        .admin-navbar .navbar-dropdown a:hover,
        .admin-navbar .navbar-dropdown button:hover {
            background: var(--primary-light);
            color: var(--primary);
        }
        .admin-navbar .navbar-dropdown .dropdown-icon {
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
        }
        @media (max-width: 900px) { .chart-grid { grid-template-columns: 1fr; } .grid-cards { grid-template-columns: repeat(2, 1fr); } }
    </style>
    @stack('head')
</head>
<body>
    <div class="admin-wrap">
        <aside class="admin-sidebar" style="display: flex; flex-direction: column;">
            <div class="brand">ERP <span>↔</span> Opera</div>
            <div class="nav-group">Monitoring</div>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('admin.execution-history') }}" class="{{ request()->routeIs('admin.execution-history') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Execution history</span>
            </a>
            <a href="{{ route('admin.failed-records') }}" class="{{ request()->routeIs('admin.failed-records') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Failed records</span>
            </a>
            <a href="{{ route('admin.failed-jobs') }}" class="{{ request()->routeIs('admin.failed-jobs') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Failed jobs</span>
            </a>
            <div class="nav-group">Data &amp; config</div>
            <a href="{{ route('admin.uploads.index') }}" class="{{ request()->routeIs('admin.uploads.*') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 12V4m0 8l-3-3m3 3l3-3"/></svg>
                <span>File uploads</span>
            </a>
            <a href="{{ route('admin.mapping.edit') }}" class="{{ request()->routeIs('admin.mapping.*') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A2 2 0 013 15.382V6.618a2 2 0 011.553-1.894L9 2m0 18l6-3m-6 3V2m6 15l5.447-2.724A2 2 0 0021 12.382V3.618a2 2 0 00-1.553-1.894L15 0m0 17V0m0 0L9 2"/></svg>
                <span>Field mapping</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5V4H2v16h5m10 0v-2a4 4 0 00-4-4H11a4 4 0 00-4 4v2m10 0H7m8-12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Admin users</span>
            </a>
            <a href="{{ route('admin.payload-audit') }}" class="{{ request()->routeIs('admin.payload-audit') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Payload audit</span>
            </a>
            <a href="{{ route('admin.opera-credentials.edit') }}" class="{{ request()->routeIs('admin.opera-credentials.*') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a4 4 0 00-4 4v2a4 4 0 004 4m0-10a4 4 0 014 4v2a4 4 0 01-4 4m0 2v2m-6-4h12"/></svg>
                <span>Opera credentials</span>
            </a>
            <a href="{{ route('admin.configuration') }}" class="{{ request()->routeIs('admin.configuration') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Configuration</span>
            </a>
            <a href="{{ route('admin.logs') }}" class="{{ request()->routeIs('admin.logs') ? 'active' : '' }}">
                <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                <span>Application logs</span>
            </a>
            <div class="sidebar-footer">
                <form method="post" action="{{ route('admin.logout') }}" style="margin: 0.25rem 0 0 0;">
                    @csrf
                    <button type="submit" style="background: none; border: none; color: var(--sidebar-text); cursor: pointer; padding: 0.5rem 1.25rem; font-size: 0.9rem; text-align: left; width: 100%; font-family: inherit;">
                        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>
        <main class="admin-main">
            <nav class="admin-navbar">
                <span class="navbar-datetime" id="navbar-datetime" aria-live="polite"></span>
                <div class="navbar-user">
                    <button type="button" class="navbar-user-trigger" id="navbar-user-trigger" aria-expanded="false" aria-haspopup="true" aria-controls="navbar-dropdown">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span>{{ Auth::guard('admin')->user()->name ?? Auth::guard('admin')->user()->email }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:0.9rem;height:0.9rem"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="navbar-dropdown" id="navbar-dropdown" role="menu" aria-labelledby="navbar-user-trigger">
                        <a href="{{ route('admin.profile.edit') }}" role="menuitem">
                            <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Profile
                        </a>
                        <form method="post" action="{{ route('admin.logout') }}" role="none" style="padding:0;margin:0">
                            @csrf
                            <button type="submit" role="menuitem">
                                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </nav>
            <div class="admin-main-inner">
                @if (session('message'))
                    <div class="message">{{ session('message') }}</div>
                @endif
                @yield('content')
                <footer class="admin-main-footer">
                    Copyright 2026 - Developed by AD Hospitality Consulting Services
                </footer>
            </div>
        </main>
    </div>
    <script>
(function () {
    var el = document.getElementById('navbar-datetime');
    var trigger = document.getElementById('navbar-user-trigger');
    var dropdown = document.getElementById('navbar-dropdown');
    function formatDateTime() {
        var d = new Date();
        return d.toLocaleDateString(undefined, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' }) + ' ' +
            d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    if (el) {
        el.textContent = formatDateTime();
        setInterval(function () { el.textContent = formatDateTime(); }, 1000);
    }
    if (trigger && dropdown) {
        trigger.addEventListener('click', function () {
            var open = dropdown.classList.toggle('is-open');
            trigger.setAttribute('aria-expanded', open);
        });
        document.addEventListener('click', function (e) {
            if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }
})();
    </script>
    @stack('scripts')
</body>
</html>
