@extends('layouts.app')

@section('content')
<div class="space-y-10">
    {{-- Hero --}}
    <section class="rounded-2xl bg-gradient-to-br from-indigo-600 to-indigo-800 px-6 py-10 text-white sm:px-10">
        <h1 class="max-w-2xl text-3xl font-bold leading-tight sm:text-4xl">Semua Jasa, Satu Platform.</h1>
        <p class="mt-2 max-w-xl text-indigo-100">Dari cuci AC sampai pembuatan website — provider terverifikasi, pembayaran aman, bergaransi.</p>
        <form action="{{ route('web.explore') }}" method="GET" class="mt-6 flex max-w-xl overflow-hidden rounded-full bg-white p-1">
            <input type="search" name="q" placeholder="Apa yang butuh dikerjakan?" class="w-full px-4 text-sm text-slate-800 outline-none">
            <button class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-semibold hover:bg-indigo-700">Cari</button>
        </form>
    </section>

    {{-- Active order banner --}}
    @if ($activeOrder)
    <a href="{{ route('web.orders.show', $activeOrder->id) }}" class="flex items-center justify-between rounded-xl border border-amber-300 bg-amber-50 px-5 py-4">
        <div class="text-sm">
            <p class="font-semibold text-amber-900">Pesanan aktif: {{ $activeOrder->service?->title ?? $activeOrder->code }}</p>
            <p class="text-amber-700">{{ ucfirst(str_replace('_', ' ', $activeOrder->status)) }}</p>
        </div>
        <span class="text-sm font-semibold text-amber-900">Lacak →</span>
    </a>
    @endif

    {{-- Categories --}}
    <section>
        <h2 class="text-lg font-bold">Kategori Jasa</h2>
        <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5 lg:grid-cols-7">
            @foreach ($categories as $category)
            <a href="{{ route('web.explore', ['category' => $category->slug]) }}"
               class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white p-4 text-center hover:border-indigo-400 hover:shadow">
                <span class="text-2xl">{{ $category->icon ?? '🛠️' }}</span>
                <span class="text-xs font-medium leading-tight">{{ $category->name }}</span>
            </a>
            @endforeach
        </div>
    </section>

    {{-- Popular services --}}
    <section>
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold">Jasa Populer</h2>
            <a href="{{ route('web.explore', ['sort' => 'rating']) }}" class="text-sm font-medium text-indigo-600 hover:underline">Lihat semua</a>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($services as $service)
            <a href="{{ route('web.service', $service->slug) }}" class="group overflow-hidden rounded-xl border border-slate-200 bg-white hover:shadow-md">
                <div class="flex h-36 items-center justify-center bg-gradient-to-br from-indigo-100 to-teal-100 text-4xl">{{ $service->category->icon ?? '🛠️' }}</div>
                <div class="p-4">
                    <p class="line-clamp-1 text-sm font-semibold group-hover:text-indigo-600">{{ $service->title }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $service->partner->display_name }} @if($service->partner->isVerified()) <span class="text-teal-600">✔</span> @endif</p>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span class="font-semibold text-slate-900">Rp{{ number_format($service->base_price, 0, ',', '.') }}{{ $service->unit_label ? '/'.$service->unit_label : '' }}</span>
                        <span class="text-amber-500">★ {{ $service->partner->rating_avg }} ({{ $service->partner->rating_count }})</span>
                    </div>
                </div>
            </a>
            @empty
            <p class="col-span-4 rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Belum ada jasa aktif. Partner terverifikasi dapat mempublikasikan jasa lewat API partner.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
