<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0F172A">
    <title>@yield('title', 'Partner Center | Jasapedia')</title>
    <link rel="icon" href="{{ asset('branding/favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen">
    {{-- Sidebar (desktop) --}}
    <aside class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col overflow-y-auto bg-slate-900 p-4 text-slate-300 lg:flex" aria-label="Navigasi Partner Center">
        <a href="{{ route('web.partner.dashboard') }}" class="mb-6 flex items-center gap-2 px-2 pt-1">
            <x-brand.logo class="h-8 w-8"/>
            <div>
                <p class="text-sm font-extrabold text-white">Partner Center</p>
                <p class="text-[10px] text-slate-400">Jasapedia for Providers</p>
            </div>
        </a>
        @php
            $partnerNav = [
                ['Ringkasan', 'web.partner.dashboard', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'],
                ['Pesanan', 'web.partner.orders', '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/>'],
                ['Jasa Saya', 'web.partner.services', '<path d="M20.59 13.41 12 22l-8.59-8.59A2 2 0 0 1 3 12V5a2 2 0 0 1 2-2h7a2 2 0 0 1 1.41.59z"/><circle cx="7.5" cy="7.5" r="1" fill="currentColor"/>'],
                ['Kebutuhan (RFQ)', 'web.partner.rfqs', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
                ['Penawaran Saya', 'web.partner.quotations', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'],
                ['Proyek', 'web.partner.projects', '<path d="M3 3h18v18H3z"/><path d="M3 9h18"/>'],
                ['Keuangan', 'web.partner.finance', '<circle cx="12" cy="12" r="10"/><path d="M12 6v12M15 9.5c0-1-1.3-2-3-2s-3 .8-3 2 1 1.8 3 2 3 1 3 2-1.3 2-3 2-3-1-3-2"/>'],
                ['Ulasan', 'web.partner.reviews', '<path d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/>'],
            ];
    $currentRoute = request()->routeIs('web.partner.dashboard') ? 'web.partner.dashboard' : request()->route()->getName();
        @endphp
        <nav class="flex-1 space-y-0.5">
            @foreach($partnerNav as [$label, $route, $path])
                <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition {{ $currentRoute === $route ? 'bg-teal-600 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                    <svg class="h-4.5 w-4.5 shrink-0" style="height:18px;width:18px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">{{ $path }}</svg>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <a href="{{ route('web.home') }}" class="mt-4 rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-slate-800 hover:text-white">&larr; Kembali ke Jasapedia</a>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Topbar --}}
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex items-center gap-3 px-4 py-3">
                <x-brand.wordmark class="lg:hidden"/>
                <h1 class="hidden text-sm font-bold text-slate-500 lg:block">Partner Center</h1>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('web.partner.finance') }}" class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-bold text-white hover:bg-teal-700">Tarik Dana</a>
                    <x-ui.avatar :name="auth()->user()->name" size="sm"/>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            @yield('partner-content')
        </main>
    </div>
</div>

{{-- Mobile bottom nav --}}
<nav class="fixed bottom-0 left-0 z-40 grid w-full grid-cols-5 border-t border-slate-200 bg-white/95 backdrop-blur lg:hidden" aria-label="Partner navigasi">
    @php
        $pnavMobile = [
            ['Beranda', 'web.partner.dashboard', '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
            ['Pesanan', 'web.partner.orders', '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>'],
            ['Jasa', 'web.partner.services', '<path d="M20.59 13.41 12 22l-8.59-8.59A2 2 0 0 1 3 12V5a2 2 0 0 1 2-2h7a2 2 0 0 1 1.41.59z"/>'],
            ['Keuangan', 'web.partner.finance', '<circle cx="12" cy="12" r="10"/><path d="M12 6v12"/>'],
            ['Ulasan', 'web.partner.reviews', '<path d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/>'],
        ];
    @endphp
    @foreach($pnavMobile as [$label, $route, $path])
        <a href="{{ route($route) }}" class="flex flex-col items-center gap-0.5 py-2 text-[10px] font-semibold {{ request()->routeIs($route) ? 'text-teal-700' : 'text-slate-500' }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{{ $path }}</svg>
            {{ $label }}
        </a>
    @endforeach
</nav>

<x-ui.toast/>
</body>
</html>
