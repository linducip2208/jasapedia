<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin | Jasapedia')</title>
    <link rel="icon" href="{{ asset('branding/favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-200 antialiased">
<div class="flex min-h-screen">
    <aside class="sticky top-0 hidden h-screen w-60 shrink-0 overflow-y-auto bg-slate-900 p-4 lg:flex lg:flex-col" aria-label="Navigasi admin">
        <p class="mb-6 px-2 text-sm font-extrabold tracking-tight text-white">
            <span class="text-teal-400">Jasapedia</span> Admin
        </p>
        @php
            $adminNav = [
                'OVERVIEW' => [['Dashboard', 'web.admin.dashboard']],
                'OPERATIONS' => [['Pesanan', 'web.admin.orders'], ['Penyedia', 'web.admin.partners']],
                'TRUST & SAFETY' => [['Sengketa', 'web.admin.disputes']],
                'FINANCE' => [['Keuangan', 'web.admin.finance'], ['Pengguna', 'web.admin.users']],
            ];
    $active = request()->route()->getName();
        @endphp
        <nav class="flex-1 space-y-5">
            @foreach($adminNav as $group => $items)
                <div>
                    <p class="mb-1.5 px-3 text-[10px] font-black uppercase tracking-widest text-slate-500">{{ $group }}</p>
                    @foreach($items as [$label, $route])
                        <a href="{{ route($route) }}" class="block rounded-xl px-3 py-2 text-sm font-semibold transition {{ $active === $route ? 'bg-teal-600/20 text-teal-300 ring-1 ring-teal-500/40' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </div>
            @endforeach
        </nav>
        <a href="{{ route('web.home') }}" class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-400 hover:bg-slate-800 hover:text-white">&larr; Keluar ke storefront</a>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-900/95 backdrop-blur">
            <div class="flex items-center gap-3 px-4 py-3">
                <p class="text-sm font-bold text-white lg:hidden">Jasapedia Admin</p>
                <div class="ml-auto flex items-center gap-2.5">
                    <x-ui.avatar :name="auth()->user()->name" size="sm"/>
                </div>
            </div>
        </header>
        <main class="flex-1 p-4 sm:p-6">
            @yield('admin-content')
        </main>
    </div>
</div>
<x-ui.toast/>
</body>
</html>
