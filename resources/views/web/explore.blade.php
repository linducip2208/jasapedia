@extends('layouts.app')

@section('title', ($q ? 'Cari "'.e($q).'"' : 'Jelajahi Jasa').' | Jasapedia')

@section('content')
<div class="flex flex-col gap-6 lg:flex-row">
    {{-- FILTER SIDEBAR (desktop) --}}
    <aside class="hidden w-72 shrink-0 lg:block" aria-label="Filter">
        <x-ui.card>
            <form method="GET" action="{{ route('web.explore') }}">
                <input type="hidden" name="q" value="{{ $q }}">
                <h2 class="font-bold text-slate-900">Filter</h2>

                <div class="mt-4">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Kategori</p>
                    <select name="category" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Semua kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}" @selected(($filters['category'] ?? '') === $cat->slug)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-4">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Rentang Harga</p>
                    <div class="flex items-center gap-2">
                        <input type="number" name="min_price" value="{{ $filters['min_price'] ?? '' }}" placeholder="Min" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" min="0"/>
                        <span class="text-slate-400">–</span>
                        <input type="number" name="max_price" value="{{ $filters['max_price'] ?? '' }}" placeholder="Maks" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" min="0"/>
                    </div>
                </div>

                <div class="mt-4">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Rating</p>
                    <select name="min_rating" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Semua rating</option>
                        <option value="4.5" @selected(($filters['min_rating'] ?? '') === '4.5')>4.5+ bintang</option>
                        <option value="4.0" @selected(($filters['min_rating'] ?? '') === '4.0')>4.0+ bintang</option>
                        <option value="3.5" @selected(($filters['min_rating'] ?? '') === '3.5')>3.5+ bintang</option>
                    </select>
                </div>

                <div class="mt-4 space-y-2.5">
                    <x-ui.checkbox name="verified" label="Penyedia terverifikasi" :checked="! empty($filters['verified'])"/>
                    <x-ui.checkbox name="emergency" label="Siap darurat 24/7" :checked="! empty($filters['emergency'])"/>
                    <x-ui.checkbox name="instant" label="Bisa instan booking" :checked="! empty($filters['instant'])"/>
                    <x-ui.checkbox name="warranty" label="Ada garansi" :checked="! empty($filters['warranty'])"/>
                </div>

                <x-ui.button type="submit" full class="mt-5">Terapkan Filter</x-ui.button>
                <a href="{{ route('web.explore') }}" class="mt-2 block text-center text-sm text-slate-500 hover:text-teal-700">Reset</a>
            </form>
        </x-ui.card>
    </aside>

    {{-- RESULTS --}}
    <div class="min-w-0 flex-1">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-extrabold text-slate-900">{{ $q ? 'Hasil untuk "'.e($q).'"' : 'Jelajahi Jasa' }}</h1>
                <p class="text-sm text-slate-500">{{ number_format($services->total()) }} jasa ditemukan</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="flex h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-semibold text-slate-700 lg:hidden" @click="$store.ui.filterOpen = true" aria-label="Buka filter">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M7 12h10M10 18h4"/></svg>Filter
                </button>
                <form method="GET" class="flex items-center gap-2">
                    @foreach(['q', 'category', 'min_price', 'max_price', 'verified', 'emergency', 'instant', 'warranty', 'min_rating'] as $keep)
                        @if(!empty($filters[$keep]))<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif
                    @endforeach
                    <select name="sort" onchange="this.form.submit()" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700" aria-label="Urutkan">
                        @php $sorts = ['' => 'Direkomendasikan', 'rating' => 'Rating tertinggi', 'price_asc' => 'Harga terendah', 'price_desc' => 'Harga tertinggi', 'newest' => 'Terbaru']; @endphp
                        @foreach($sorts as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['sort'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        {{-- Active filter chips --}}
        @if(!empty($filters['category']) || !empty($filters['verified']) || !empty($filters['emergency']) || !empty($filters['instant']) || !empty($filters['warranty']) || !empty($filters['min_rating']))
            <div class="mb-4 flex flex-wrap gap-2">
                @if(!empty($filters['category']))<x-ui.chip active>Kategori: {{ $filters['category'] }}</x-ui.chip>@endif
                @if(!empty($filters['verified']))<x-ui.chip active>Terverifikasi</x-ui.chip>@endif
                @if(!empty($filters['emergency']))<x-ui.chip active>Darurat 24/7</x-ui.chip>@endif
                @if(!empty($filters['instant']))<x-ui.chip active>Instan</x-ui.chip>@endif
                @if(!empty($filters['warranty']))<x-ui.chip active>Garansi</x-ui.chip>@endif
                @if(!empty($filters['min_rating']))<x-ui.chip active>Rating {{ $filters['min_rating'] }}+</x-ui.chip>@endif
                <a href="{{ route('web.explore') }}" class="text-xs font-bold text-rose-600 hover:underline">Hapus semua</a>
            </div>
        @endif

        @forelse($services as $service)
            @if($loop->first)<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">@endif
            <x-ui.service-card :service="$service"/>
            @if($loop->last)</div>@endif
        @empty
            <x-ui.empty-state title="Tidak ada jasa yang cocok" description="Coba ubah kata kunci atau hapus beberapa filter." actionUrl="{{ route('web.explore') }}" actionLabel="Hapus Filter"/>
        @endforelse

        <x-ui.pagination :paginator="$services"/>
    </div>
</div>

{{-- Mobile filter drawer --}}
<div x-data x-show="$store.ui.filterOpen" x-cloak class="fixed inset-0 z-50 lg:hidden">
    <div class="absolute inset-0 bg-slate-900/50" @click="$store.ui.filterOpen = false"></div>
    <div class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto rounded-t-3xl bg-white p-5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-bold text-slate-900">Filter</h2>
            <button @click="$store.ui.filterOpen = false" class="rounded-full p-2 text-slate-400" aria-label="Tutup filter">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="GET" action="{{ route('web.explore') }}">
            <input type="hidden" name="q" value="{{ $q }}">
            <select name="category" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">Semua kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->slug }}" @selected(($filters['category'] ?? '') === $cat->slug)>{{ $cat->name }}</option>
                @endforeach
            </select>
            <div class="mt-3 flex items-center gap-2">
                <input type="number" name="min_price" value="{{ $filters['min_price'] ?? '' }}" placeholder="Harga min" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" min="0"/>
                <input type="number" name="max_price" value="{{ $filters['max_price'] ?? '' }}" placeholder="Harga maks" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" min="0"/>
            </div>
            <div class="mt-3 space-y-2.5">
                <x-ui.checkbox name="verified" label="Terverifikasi" :checked="! empty($filters['verified'])"/>
                <x-ui.checkbox name="emergency" label="Darurat 24/7" :checked="! empty($filters['emergency'])"/>
                <x-ui.checkbox name="instant" label="Instan" :checked="! empty($filters['instant'])"/>
            </div>
            <x-ui.button type="submit" full class="mt-4">Terapkan</x-ui.button>
        </form>
    </div>
</div>
@endsection
