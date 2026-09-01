@extends('layouts.app')

@section('title', 'Explore Jasa')

@section('content')
<div class="grid gap-6 lg:grid-cols-[240px_1fr]">
    {{-- Filters --}}
    <aside class="h-fit space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        <form method="GET" action="{{ route('web.explore') }}" class="space-y-5 text-sm">
            <input type="hidden" name="q" value="{{ request('q') }}">
            <div>
                <p class="mb-2 font-semibold">Kategori</p>
                <select name="category" class="w-full rounded-lg border-slate-300">
                    <option value="">Semua</option>
                    @foreach ($categories as $category)
                    <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <p class="mb-2 font-semibold">Harga</p>
                <div class="flex gap-2">
                    <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" class="w-full rounded-lg border-slate-300">
                    <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Maks" class="w-full rounded-lg border-slate-300">
                </div>
            </div>
            <div>
                <p class="mb-2 font-semibold">Urutkan</p>
                <select name="sort" class="w-full rounded-lg border-slate-300">
                    <option value="">Terbaru</option>
                    <option value="rating" @selected(request('sort') === 'rating')>Rating tertinggi</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Harga terendah</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Harga tertinggi</option>
                </select>
            </div>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="emergency" value="1" @checked(request('emergency')) class="rounded border-slate-300">
                <span>Butuh segera (ASAP)</span>
            </label>
            <button class="w-full rounded-lg bg-indigo-600 py-2 font-semibold text-white hover:bg-indigo-700">Terapkan</button>
        </form>
    </aside>

    {{-- Results --}}
    <section>
        <p class="text-sm text-slate-500">{{ $services->total() }} jasa ditemukan</p>
        <div class="mt-4 space-y-3">
            @forelse ($services as $service)
            <a href="{{ route('web.service', $service->slug) }}" class="flex gap-4 rounded-xl border border-slate-200 bg-white p-4 hover:shadow-md">
                <div class="flex h-24 w-32 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-100 to-teal-100 text-3xl">{{ $service->category->icon ?? '🛠️' }}</div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold">{{ $service->title }}</p>
                        <p class="shrink-0 font-bold text-indigo-700">Rp{{ number_format($service->base_price, 0, ',', '.') }}{{ $service->unit_label ? '/'.$service->unit_label : '' }}</p>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $service->partner->display_name }} @if($service->partner->isVerified())<span class="text-teal-600">✔ Terverifikasi</span>@endif · {{ $service->category->name }}</p>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $service->description }}</p>
                    <div class="mt-2 flex gap-3 text-xs text-slate-500">
                        <span class="text-amber-500">★ {{ $service->partner->rating_avg }}</span>
                        <span>{{ $service->partner->completed_jobs }} pekerjaan selesai</span>
                        @if($service->emergency_capable)<span class="font-semibold text-rose-600">⚡ ASAP</span>@endif
                    </div>
                </div>
            </a>
            @empty
            <div class="rounded-xl border border-dashed border-slate-300 p-12 text-center">
                <p class="text-3xl">🔍</p>
                <p class="mt-2 font-semibold">Tidak ada hasil</p>
                <p class="text-sm text-slate-500">Coba ubah kata kunci atau filter.</p>
            </div>
            @endforelse
        </div>
        <div class="mt-6">{{ $services->withQueryString()->links() }}</div>
    </section>
</div>
@endsection
