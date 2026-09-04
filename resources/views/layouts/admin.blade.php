<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin | Jasapedia')</title>
    <link rel="icon" href="{{ asset('branding/favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="app-wrapper sidebar-expand-lg layout-fixed hold-transition">
@php
    $adminNav = [
        'Overview' => [['Dashboard', 'web.admin.dashboard', 'M3 3h8v8H3zM13 3h8v8h-8zM13 13h8v8h-8zM3 13h8v8H3z']],
        'Operasi' => [
            ['Pesanan', 'web.admin.orders', 'M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0'],
            ['Penyedia', 'web.admin.partners', 'M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
        ],
        'Trust & Safety' => [['Sengketa', 'web.admin.disputes', 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 8v4M12 16h.01']],
        'Keuangan' => [
            ['Ledger & Withdrawal', 'web.admin.finance', 'M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
            ['Pengguna', 'web.admin.users', 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8'],
        ],
    ];
    $active = request()->route()->getName();
    $initial = auth()->user() ? mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) : '?';
@endphp
<aside class="app-sidebar shadow" aria-label="Navigasi admin">
    <div class="sidebar-brand">
        <a href="{{ route('web.admin.dashboard') }}" class="brand-link">
            <span class="brand-text fw-bold">Jasapedia <span class="text-brand">Admin</span></span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" role="menu">
                @foreach($adminNav as $group => $items)
                    <li class="nav-header text-uppercase">{{ $group }}</li>
                    @foreach($items as [$label, $route, $icon])
                        <li class="nav-item">
                            <a href="{{ route($route) }}" class="nav-link {{ $active === $route ? 'active' : '' }}" {{ $active === $route ? 'aria-current="page"' : '' }}>
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                                <p>{{ $label }}</p>
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </nav>
        <div class="px-3 py-2 small">
            <a href="{{ route('web.home') }}" class="text-body-secondary text-decoration-none">&larr; Keluar ke storefront</a>
        </div>
    </div>
</aside>

<header class="app-header">
    <nav class="navbar navbar-expand" aria-label="Bar atas admin">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <button class="nav-link btn btn-link px-1" type="button" data-lte-toggle="sidebar" aria-label="Buka atau tutup menu">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                    </button>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <span class="admin-avatar d-inline-flex align-items-center justify-content-center rounded-circle fw-bold" title="{{ auth()->user()->name ?? '' }}">{{ $initial }}</span>
                </li>
            </ul>
        </div>
    </nav>
</header>

<main class="app-main" id="main-content">
    <div class="app-content px-3 px-sm-4 py-3">
        @if(session('success') || session('info') || session('warning') || $errors->any())
            @php
                $toastText = session('success') ?? session('info') ?? session('warning') ?? ($errors->any() ? 'Periksa kembali isian formulir.' : null);
                $toastTone = $errors->any() && ! session('success') ? 'danger' : (session('warning') ? 'warning' : (session('info') ? 'info' : 'success'));
            @endphp
            <div class="alert alert-{{ $toastTone }} alert-dismissible fade show" role="status" aria-live="polite">
                {{ $toastText }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif
        @yield('admin-content')
    </div>
</main>

<footer class="app-footer">
    <div class="float-end d-none d-sm-inline">Jasapedia Command Center</div>
    <strong>&copy; {{ date('Y') }} Jasapedia Admin</strong>
</footer>
</body>
</html>
