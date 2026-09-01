@php
$user = auth()->user();
$notifCount = $user ? \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', $user->id)->whereNull('read_at')->count() : 0;
$notifItems = $user ? \Illuminate\Support\Facades\DB::table('notifications')->where('user_id', $user->id)->whereNull('read_at')->latest()->take(5)->get() : collect();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0D9488">
    <title>@yield('title', 'Jasapedia — Semua Jasa, Satu Platform')@yield('titleSuffix', '')</title>
    <meta name="description" content="@yield('meta_description', 'Jasapedia — marketplace jasa terlengkap Indonesia. Servis rumah, teknisi, freelancer, proyek digital, hingga kebutuhan perusahaan.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('branding/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('branding/favicon-32x32.png') }}" sizes="32x32" type="image/png">
    <link rel="icon" href="{{ asset('branding/favicon-192x192.png') }} " sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('branding/apple-touch-icon.png') }}">
    <meta property="og:site_name" content="Jasapedia">
    <meta property="og:title" content="@yield('title', 'Jasapedia — Semua Jasa, Satu Platform')">
    <meta property="og:description" content="@yield('meta_description', 'Semua Jasa, Satu Platform.')">
    <meta property="og:image" content="{{ asset('branding/og-default.png') }}">
    <meta property="og:type" content="website">
    @stack('meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 pb-16 text-slate-800 antialiased md:pb-0">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-[70] focus:rounded-lg focus:bg-teal-700 focus:px-4 focus:py-2 focus:text-white">Lewati ke konten</a>

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2.5 lg:gap-5">
        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Buka menu kategori" @click="$store.ui.catOpen = ! $store.ui.catOpen">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>

        <x-brand.wordmark class="shrink-0"/>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Kategori">
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" aria-expanded="false">
                    Kategori
                    <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open" x-cloak x-transition class="absolute left-0 top-full z-50 mt-1 w-72 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                    <a href="{{ route('web.explore') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 hover:bg-teal-50 hover:text-teal-800">Semua Kategori</a>
                    @foreach(($categories ?? collect())->take(12) as $cat)
                        <a href="{{ route('web.explore', ['category' => $cat->slug]) }}" class="block rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-teal-50 hover:text-teal-800">{{ $cat->name }}</a>
                    @endforeach
                </div>
            </div>
        </nav>

        <x-ui.search-input class="hidden flex-1 md:flex"/>

        <div class="ml-auto flex items-center gap-0.5 sm:gap-1">
            @guest
                <a href="{{ route('login') }}" class="hidden h-10 items-center rounded-xl px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:flex">Masuk</a>
                <a href="{{ route('register') }}" class="hidden h-10 items-center rounded-xl bg-teal-600 px-4 text-sm font-semibold text-white hover:bg-teal-700 sm:flex">Daftar</a>
            @endguest
            @auth
                <a href="{{ route('web.requests.create') }}" class="hidden h-10 items-center rounded-xl border border-teal-600 px-3.5 text-sm font-semibold text-teal-700 hover:bg-teal-50 xl:flex">Jadi Penyedia</a>
                <a href="{{ route('web.chat.index') }}" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100" aria-label="Chat">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </a>
                <a href="{{ route('web.orders') }}" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100" aria-label="Pesanan">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>
                </a>
                <a href="{{ route('web.favorites') }}" class="relative hidden h-10 w-10 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 sm:flex" aria-label="Favorit">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                </a>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100" aria-label="Notifikasi" aria-expanded="false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        @if($notifCount > 0)<span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-0.5 text-[9px] font-bold text-white">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>@endif
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-full z-50 mt-1 w-80 rounded-2xl border border-slate-200 bg-white shadow-xl">
                        <p class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-900">Notifikasi</p>
                        <div class="max-h-80 overflow-y-auto">
                            @forelse($notifItems as $n)
                                <article class="flex gap-3 px-4 py-3">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-teal-700">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-800">{{ $n->title }}</p>
                                        @if($n->body)<p class="mt-0.5 text-sm text-slate-500">{{ $n->body }}</p>@endif
                                        <p class="mt-1 text-xs text-slate-400">{{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}</p>
                                    </div>
                                </article>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-slate-400">Tidak ada notifikasi baru</p>
                            @endforelse
                        </div>
                        <a href="{{ route('web.account.notifications') }}" class="block border-t border-slate-100 px-4 py-2.5 text-center text-sm font-bold text-teal-700 hover:bg-teal-50">Lihat semua</a>
                    </div>
                </div>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false" class="ml-0.5 flex items-center rounded-full focus-visible:ring-2 focus-visible:ring-teal-600" aria-label="Menu akun" aria-expanded="false">
                        <x-ui.avatar :name="$user->name" size="sm"/>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-full z-50 mt-1 w-56 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg">
                        <div class="border-b border-slate-100 px-4 pb-2 pt-1">
                            <p class="truncate text-sm font-bold text-slate-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                        <a href="{{ route('web.account.dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Dashboard Akun</a>
                        <a href="{{ route('web.orders') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Pesanan Saya</a>
                        <a href="{{ route('web.account.profile') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Profil</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="w-full px-4 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">Keluar</button>
                        </form>
                    </div>
                </div>
            @endauth
        </div>
    </div>
    <x-ui.search-input class="mx-4 mb-2.5 flex md:hidden"/>
</header>

<div x-data x-show="$store.ui.catOpen" x-cloak class="fixed inset-0 z-50 lg:hidden">
    <div class="absolute inset-0 bg-slate-900/50" @click="$store.ui.catOpen = false"></div>
    <nav class="absolute left-0 top-0 h-full w-80 overflow-y-auto bg-white p-4 shadow-xl" aria-label="Kategori">
        <div class="mb-3 flex items-center justify-between">
            <p class="font-bold text-slate-900">Kategori Jasa</p>
            <button @click="$store.ui.catOpen = false" class="rounded-full p-2 text-slate-400 hover:bg-slate-100" aria-label="Tutup">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <a href="{{ route('web.explore') }}" class="block rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-800 hover:bg-teal-50">Semua Kategori</a>
        @foreach(($categories ?? collect()) as $cat)
            <a href="{{ route('web.explore', ['category' => $cat->slug]) }}" class="block rounded-xl px-3 py-2.5 text-sm text-slate-700 hover:bg-teal-50">{{ $cat->name }}</a>
        @endforeach
    </nav>
</div>

<main id="main" class="mx-auto max-w-7xl px-4 py-6">
    @yield('content')
</main>

@auth
<div class="fixed bottom-0 left-1/2 z-40 w-full -translate-x-1/2 border-t border-slate-200 bg-white/95 backdrop-blur md:hidden">
    <nav class="mx-auto grid max-w-lg grid-cols-5" aria-label="Navigasi utama">
        @php
            $navItems = [
                'home' => ['Beranda', 'web.home', '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>'],
                'explore' => ['Jelajahi', 'web.explore', '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>'],
                'orders' => ['Pesanan', 'web.orders', '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/>'],
                'chat' => ['Chat', 'web.chat.index', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
                'account' => ['Akun', 'web.account.dashboard', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
            ];
    $current = request()->routeIs('web.home') ? 'home' : (request()->routeIs('web.explore*') ? 'explore' : (request()->routeIs('web.orders*') ? 'orders' : (request()->routeIs('web.chat*') ? 'chat' : 'account')));
        @endphp
        @foreach($navItems as $key => [$label, $route, $paths])
            <a href="{{ route($route) }}" class="flex flex-col items-center gap-0.5 py-2 text-[10px] font-semibold {{ $current === $key ? 'text-teal-700' : 'text-slate-500' }}" @if($current === $key) aria-current="page" @endif>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">{{ $paths }}</svg>
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
@endauth

<footer class="mt-14 hidden border-t border-slate-200 bg-white md:block">
    <div class="mx-auto max-w-7xl px-4 py-12">
        <div class="grid gap-10 md:grid-cols-[1.4fr_repeat(4,1fr)]">
            <div>
                <x-brand.wordmark/>
                <p class="mt-3 max-w-xs text-sm text-slate-500">Semua Jasa, Satu Platform. Dari servis rumah sampai proyek digital dan kebutuhan perusahaan.</p>
                <div class="mt-4 flex gap-2">
                    <img src="{{ asset('branding/favicon-32x32.png') }}" alt="Jasapedia" class="h-8 w-8 rounded-lg" loading="lazy"/>
                </div>
            </div>
            @php
                $footerGroups = [
                    'Jasapedia' => [['Tentang', 'tentang-kami'], ['Karier', 'karier'], ['Blog', null, 'blog']],
                    'Customer' => [['Cara Memesan', 'cara-memesan'], ['Pembayaran', 'pembayaran'], ['Refund', 'kebijakan-refund'], ['Jaminan Jasapedia', 'jaminan-jasapedia']],
                    'Provider' => [['Jadi Penyedia', 'jadi-penyedia'], ['Panduan Partner', 'panduan-partner']],
                    'Business' => [['Jasapedia Business', 'jasapedia-business'], ['Corporate Procurement', 'corporate-procurement']],
                    'Support' => [['Help Center', 'help-center'], ['Contact', 'contact'], ['Terms', 'terms'], ['Privacy', 'privacy']],
                ];
            @endphp
            @foreach($footerGroups as $group => $links)
                <div>
                    <p class="text-sm font-bold text-slate-900">{{ $group }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-500">
                        @foreach($links as [$label, $slug])
                            <li><a href="{{ $slug ? route('web.page', $slug) : route('web.blog.index') }}" class="hover:text-teal-700">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <div class="mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-6 text-xs text-slate-400">
            <p>&copy; {{ date('Y') }} Jasapedia. Semua hak dilindungi.</p>
            <p>Dibuat di Indonesia 🇮🇩</p>
        </div>
    </div>
</footer>

<x-ui.toast/>
</body>
</html>
