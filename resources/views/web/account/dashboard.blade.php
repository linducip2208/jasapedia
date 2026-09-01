@extends('layouts.app')

@section('title', 'Dashboard Akun | Jasapedia')

@section('content')
<h1 class="text-xl font-extrabold text-slate-900">Halo, {{ auth()->user()->first_name ?? explode(' ', auth()->user()->name)[0] }} 👋</h1>
<p class="text-sm text-slate-500">Pantau pesanan dan aktivitasmu.</p>

<div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
    <a href="{{ route('web.orders', ['status' => 'active']) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <p class="text-xs font-bold uppercase text-slate-400">Pesanan Aktif</p>
        <p class="mt-1 text-2xl font-extrabold text-teal-700">{{ $activeOrdersCount }}</p>
    </a>
    <a href="{{ route('web.orders', ['status' => 'active']) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <p class="text-xs font-bold uppercase text-slate-400">Booking Mendatang</p>
        <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $upcomingBookings->count() }}</p>
    </a>
    <a href="{{ route('web.requests.index') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <p class="text-xs font-bold uppercase text-slate-400">Kebutuhan Terbuka</p>
        <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $openRequests }}</p>
    </a>
    <a href="{{ route('web.projects.index', ['mine' => 1]) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <p class="text-xs font-bold uppercase text-slate-400">Proyek Aktif</p>
        <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $activeProjects }}</p>
    </a>
    <a href="{{ route('web.chat.index') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
        <p class="text-xs font-bold uppercase text-slate-400">Chat</p>
        <p class="mt-1 text-2xl font-extrabold text-slate-900">Buka</p>
    </a>
</div>

@if($upcomingBookings->isNotEmpty())
    <section class="mt-8">
        <h2 class="font-bold text-slate-800">Booking Terdekat</h2>
        <div class="mt-3 space-y-3">
            @foreach($upcomingBookings as $order)
                <x-ui.order-card :order="$order"/>
            @endforeach
        </div>
    </section>
@endif

<section class="mt-8">
    <div class="flex items-center justify-between">
        <h2 class="font-bold text-slate-800">Aktivitas Terakhir</h2>
        <a href="{{ route('web.orders') }}" class="text-sm font-bold text-teal-700 hover:underline">Semua pesanan</a>
    </div>
    <div class="mt-3 space-y-3">
        @forelse($recentActivity as $order)
            <x-ui.order-card :order="$order"/>
        @empty
            <x-ui.empty-state title="Belum ada aktivitas" description="Mulai pesan jasa pertamamu." actionUrl="{{ route('web.explore') }}" actionLabel="Cari Jasa"/>
        @endforelse
    </div>
</section>

{{-- Quick actions --}}
<section class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
    @php
        $quick = [
            ['Posting Kebutuhan', route('web.requests.create')],
            ['Buat Proyek', route('web.projects.create')],
            ['Favorit', route('web.favorites')],
            ['Jasapedia Business', route('web.business.dashboard')],
        ];
    @endphp
    @foreach($quick as [$label, $url])
        <a href="{{ $url }}" class="rounded-2xl border border-dashed border-teal-300 bg-teal-50/50 p-4 text-center text-sm font-bold text-teal-800 transition hover:bg-teal-50">{{ $label }}</a>
    @endforeach
</section>
@endsection
