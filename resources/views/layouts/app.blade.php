<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Jasapedia') — Semua Jasa, Satu Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('meta')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3">
        <a href="{{ route('web.home') }}" class="flex items-center gap-2 font-bold text-indigo-600">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white">J</span>
            <span class="hidden sm:inline">Jasapedia</span>
        </a>
        <form action="{{ route('web.explore') }}" method="GET" class="flex flex-1 items-center rounded-full border border-slate-300 bg-slate-50 px-4 py-2 focus-within:border-indigo-500">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari jasa… cuci AC, cleaning, website" class="w-full bg-transparent text-sm outline-none">
            <button class="text-slate-500 hover:text-indigo-600" aria-label="Cari">🔍</button>
        </form>
        <nav class="hidden items-center gap-5 text-sm font-medium md:flex">
            <a href="{{ route('web.home') }}" class="hover:text-indigo-600">Home</a>
            <a href="{{ route('web.explore') }}" class="hover:text-indigo-600">Explore</a>
            <a href="{{ route('web.orders') }}" class="hover:text-indigo-600">Orders</a>
            <a href="{{ route('login') }}" class="rounded-full bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Masuk</a>
        </nav>
    </div>
</header>
<main class="mx-auto max-w-6xl px-4 py-6">
    @yield('content')
</main>
<footer class="mt-12 border-t border-slate-200 bg-white">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 text-sm text-slate-600 sm:grid-cols-3">
        <div>
            <p class="font-bold text-indigo-600">Jasapedia</p>
            <p class="mt-1">Semua Jasa, Satu Platform.</p>
        </div>
        <div>
            <p class="font-semibold text-slate-800">Perusahaan</p>
            <ul class="mt-1 space-y-1">
                <li><a href="{{ route('web.page', 'tentang-kami') }}" class="hover:text-indigo-600">Tentang Kami</a></li>
                <li><a href="{{ route('web.page', 'kebijakan-refund') }}" class="hover:text-indigo-600">Kebijakan Refund</a></li>
            </ul>
        </div>
        <div>
            <p class="font-semibold text-slate-800">Bantuan</p>
            <ul class="mt-1 space-y-1">
                <li><a href="{{ route('web.page', 'panduan-pemesanan') }}" class="hover:text-indigo-600">Panduan Pemesanan</a></li>
                <li><a href="{{ route('web.page', 'faq') }}" class="hover:text-indigo-600">FAQ</a></li>
            </ul>
        </div>
    </div>
</footer>
</body>
</html>
