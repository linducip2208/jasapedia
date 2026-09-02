@extends('layouts.app')
@php
    $categories->loadMissing('children');
    $popular = $categories->take(8);
@endphp

@section('title', 'Jasapedia — Semua Jasa, Satu Platform')

@section('content')
{{-- HERO --}}
<section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-teal-700 via-teal-600 to-teal-800 px-5 py-12 text-white sm:px-10 sm:py-16">
    <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-amber-400/20 blur-2xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -left-16 h-72 w-72 rounded-full bg-white/10 blur-2xl"></div>
    <div class="relative mx-auto max-w-3xl text-center">
        <h1 class="text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">Semua Jasa, Satu Platform.</h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-teal-50/90 sm:text-lg">Dari servis rumah sampai proyek digital dan kebutuhan perusahaan.</p>
        <form action="{{ route('web.explore') }}" method="GET" role="search" class="mx-auto mt-6 flex max-w-2xl items-center gap-2 rounded-full bg-white p-1.5 pl-5 shadow-lg">
            <svg class="h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" placeholder="Cari jasa, teknisi, freelancer, programmer..." class="w-full bg-transparent text-sm text-slate-900 placeholder-slate-400 outline-none sm:text-base" aria-label="Cari jasa"/>
            <button class="shrink-0 rounded-full bg-teal-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-teal-700 sm:px-7">Cari Jasa</button>
        </form>
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2.5">
            <a href="{{ route('web.explore') }}" class="rounded-full bg-white/15 px-4 py-2 text-xs font-bold backdrop-blur hover:bg-white/25">Cari Jasa</a>
            <a href="{{ route('web.requests.create') }}" class="rounded-full bg-white/15 px-4 py-2 text-xs font-bold backdrop-blur hover:bg-white/25">Posting Kebutuhan</a>
            <a href="{{ route('web.projects.create') }}" class="rounded-full bg-white/15 px-4 py-2 text-xs font-bold backdrop-blur hover:bg-white/25">Cari Freelancer</a>
            <a href="{{ route('web.partner.onboarding') }}" class="rounded-full bg-amber-400 px-4 py-2 text-xs font-bold text-amber-950 hover:bg-amber-300">Jadi Penyedia</a>
        </div>
    </div>
</section>

{{-- HERO CATEGORIES --}}
<section class="mt-6" aria-label="Kategori populer">
    <div class="grid grid-cols-4 gap-2.5 sm:grid-cols-8">
        @forelse($popular as $cat)
            <a href="{{ route('web.explore', ['category' => $cat->slug]) }}" class="group flex flex-col items-center gap-2 rounded-2xl bg-white p-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-teal-50 text-teal-700 group-hover:bg-teal-600 group-hover:text-white">
                    <x-brand.category-icon :icon="$cat->icon" class="h-6 w-6"/>
                </span>
                <span class="line-clamp-2 text-[11px] font-semibold leading-tight text-slate-700">{{ $cat->name }}</span>
            </a>
        @empty
            <p class="col-span-8 text-center text-sm text-slate-400">Kategori belum tersedia.</p>
        @endforelse
    </div>
</section>

{{-- SUPER APP ACTIONS --}}
<section class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6" aria-label="Layanan utama">
    @php
        $actions = [
            ['Cari Jasa', 'explore', '#0D9488', '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'],
            ['Booking Teknisi', 'explore?emergency=1', '#DC2626', '<path d="M12 2v20M2 12h20"/>'],
            ['Posting Kebutuhan', 'kebutuhan/buat', '#D97706', '<path d="M12 5v14M5 12h14"/>'],
            ['Cari Freelancer', 'proyek', '#4F46E5', '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6"/>'],
            ['Buat Proyek', 'proyek/buat', '#7C3AED', '<path d="M3 3h18v18H3z"/><path d="M3 9h18"/>'],
            ['Jasapedia Business', 'business', '#0F172A', '<path d="M3 21h18M5 21V7l7-4 7 4v14"/>'],
        ];
    @endphp
    @foreach($actions as [$label, $url, $color, $path])
        <a href="{{ url($url) }}" class="flex items-center gap-2.5 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white" style="background: {{ $color }}">
                <svg class="h-4.5 w-4.5" style="height:18px;width:18px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{{ $path }}</svg>
            </span>
            <span class="text-xs font-bold leading-tight text-slate-800">{{ $label }}</span>
        </a>
    @endforeach
</section>

{{-- POPULAR SERVICES --}}
<section class="mt-10" aria-label="Jasa populer">
    <div class="mb-4 flex items-end justify-between">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 sm:text-xl">Jasa Populer</h2>
            <p class="text-sm text-slate-500">Paling banyak dicari pekan ini</p>
        </div>
        <a href="{{ route('web.explore') }}" class="text-sm font-bold text-teal-700 hover:underline">Lihat semua</a>
    </div>
    @forelse($services as $service)
        @php $first = $loop->first; @endphp
        @if($first)<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">@endif
        <x-ui.service-card :service="$service"/>
        @if($loop->last)</div>@endif
    @empty
        <x-ui.empty-state title="Belum ada jasa" description="Jasa akan muncul di sini setelah penyedia mulai berjualan." actionUrl="{{ route('web.partner.onboarding') }}" actionLabel="Jadi Penyedia Pertama"/>
    @endforelse
</section>

{{-- TOP PROVIDERS --}}
<section class="mt-10" aria-label="Penyedia teratas">
    <div class="mb-4 flex items-end justify-between">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 sm:text-xl">Penyedia Teratas</h2>
            <p class="text-sm text-slate-500">Rating dan ulasan tertinggi dari pelanggan nyata</p>
        </div>
        <a href="{{ route('web.explore', ['sort' => 'rating']) }}" class="text-sm font-bold text-teal-700 hover:underline">Lihat semua</a>
    </div>
    @forelse($topProviders as $provider)
        @php $firstProvider = $loop->first; @endphp
        @if($firstProvider)<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">@endif
        <a href="{{ route('web.provider.show', $provider) }}" class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-sm font-black text-teal-700 group-hover:bg-teal-600 group-hover:text-white">
                {{ mb_substr($provider->display_name, 0, 1) }}
            </span>
            <span class="min-w-0">
                <span class="block truncate text-sm font-bold text-slate-900">{{ $provider->display_name }}</span>
                <span class="block text-[11px] text-slate-500">{{ $provider->city ?? 'Indonesia' }}</span>
                <span class="mt-0.5 flex items-center gap-1 text-[11px] text-amber-600">
                    ★ {{ number_format($provider->rating_avg, 1) }} <span class="text-slate-400">({{ number_format($provider->rating_count) }} ulasan)</span>
                </span>
            </span>
        </a>
        @if($loop->last)</div>@endif
    @empty
        <p class="text-sm text-slate-400">Belum ada penyedia terverifikasi.</p>
    @endforelse
</section>

{{-- AVAILABLE TODAY --}}
<section class="mt-10" aria-label="Tersedia hari ini">
    <div class="mb-4 flex items-end justify-between">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 sm:text-xl">Tersedia Hari Ini</h2>
            <p class="text-sm text-slate-500">Penyedia yang sedang online dan siap dipesan</p>
        </div>
        <a href="{{ route('web.explore') }}" class="text-sm font-bold text-teal-700 hover:underline">Lihat semua</a>
    </div>
    @forelse($availableToday as $service)
        @php $firstAvail = $loop->first; @endphp
        @if($firstAvail)<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">@endif
        <x-ui.service-card :service="$service"/>
        @if($loop->last)</div>@endif
    @empty
        <p class="text-sm text-slate-400">Tidak ada penyedia online saat ini.</p>
    @endforelse
</section>

{{-- OPEN PROJECTS (MARKETPLACE) --}}
<section class="mt-10" aria-label="Proyek terbuka">
    <div class="mb-4 flex items-end justify-between">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 sm:text-xl">Proyek Terbuka</h2>
            <p class="text-sm text-slate-500">Kebutuhan klien yang sedang mencari freelancer & vendor</p>
        </div>
        <a href="{{ route('web.projects.index') }}" class="text-sm font-bold text-teal-700 hover:underline">Lihat semua</a>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        @forelse($openProjects as $project)
            <a href="{{ route('web.projects.show', $project) }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-[11px] font-bold uppercase tracking-wide text-indigo-600">{{ $project->category?->name }}</p>
                <h3 class="mt-1 line-clamp-1 font-bold text-slate-900 group-hover:text-indigo-700">{{ $project->title }}</h3>
                <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500">{{ \Illuminate\Support\Str::limit($project->description, 120) }}</p>
                <div class="mt-2.5 flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-700">
                        @if($project->budget_min && $project->budget_max)
                            Rp{{ number_format($project->budget_min / 1000) }}rb – Rp{{ number_format($project->budget_max / 1000000, 1) }}jt
                        @else
                            Nego
                        @endif
                    </span>
                    <span class="text-slate-400">{{ $project->proposals_count }} proposal</span>
                </div>
            </a>
        @empty
            <p class="text-sm text-slate-400">Belum ada proyek terbuka.</p>
        @endforelse
    </div>
</section>

{{-- HOW IT WORKS --}}
<section class="mt-10 rounded-3xl bg-white p-6 shadow-sm sm:p-10" aria-label="Cara kerja">
    <h2 class="text-center text-lg font-extrabold text-slate-900 sm:text-xl">Cara Kerja Jasapedia</h2>
    <div class="mt-8 grid gap-8 sm:grid-cols-4">
        @php
            $steps = [
                ['Cari atau Posting', 'Temukan jasa yang tepat atau posting kebutuhanmu, penyedia akan datang kepadamu.'],
                ['Bandingkan & Pilih', 'Lihat rating, ulasan asli, harga transparan, dan tingkat kepercayaan penyedia.'],
                ['Bayar Aman', 'Dana ditahan aman oleh Jasapedia sampai pekerjaan selesai dan kamu konfirmasi.'],
                ['Selesai & Ulas', 'Pekerjaan beres, penyedia dibayar, dan kamu beri ulasan untuk komunitas.'],
            ];
    @endphp
        @foreach($steps as $i => [$title, $desc])
            <div class="text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-600 text-lg font-black text-white">{{ $i + 1 }}</span>
                <h3 class="mt-3 font-bold text-slate-900">{{ $title }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- TRUST --}}
<section class="mt-10 grid gap-3 sm:grid-cols-3" aria-label="Keamanan">
    @php
        $trusts = [
            ['Pembayaran Terlindungi', 'Dana baru diteruskan ke penyedia setelah pekerjaan selesai kamu konfirmasi.'],
            ['Penyedia Terverifikasi', 'Identitas KYC/KYB diverifikasi Jasapedia sebelum bisa menerima pesanan.'],
            ['Garansi & Refund', 'Garansi jasa sesuai kategori dan refund jika pekerjaan tidak sesuai kesepakatan.'],
        ];
    @endphp
    @foreach($trusts as [$title, $desc])
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
            </span>
            <h3 class="mt-3 font-bold text-slate-900">{{ $title }}</h3>
            <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
        </div>
    @endforeach
</section>

{{-- CTA PROVIDER --}}
<section class="mt-10 overflow-hidden rounded-3xl bg-slate-900 px-6 py-10 text-white sm:px-12 sm:py-14">
    <div class="flex flex-col items-center gap-6 sm:flex-row sm:justify-between">
        <div class="max-w-xl">
            <h2 class="text-2xl font-extrabold sm:text-3xl">Punya keahlian? Mulai dapat order hari ini.</h2>
            <p class="mt-2 text-sm text-slate-300">Gratis mendaftar. Terima order dari pelanggan di sekitarmu, kelola jadwal & tim dalam satu Partner Center.</p>
        </div>
        <a href="{{ route('web.partner.onboarding') }}" class="shrink-0 rounded-xl bg-amber-400 px-7 py-3.5 font-bold text-amber-950 hover:bg-amber-300">Jadi Penyedia</a>
    </div>
</section>

{{-- BUSINESS CTA --}}
<section class="mt-4 rounded-3xl border border-teal-200 bg-teal-50 px-6 py-8 sm:px-12">
    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
        <div>
            <h2 class="text-xl font-extrabold text-teal-900">Jasapedia Business</h2>
            <p class="mt-1 text-sm text-teal-800/80">Pengadaan jasa untuk perusahaan: approval berjenjang, PO, cost center, dan vendor terkurasi.</p>
        </div>
        <a href="{{ route('web.business.dashboard') }}" class="shrink-0 rounded-xl bg-teal-700 px-6 py-3 font-bold text-white hover:bg-teal-800">Pelajari Business</a>
    </div>
</section>
@endsection
