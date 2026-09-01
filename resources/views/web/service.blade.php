@extends('layouts.app')
@php
    $media = $service->media ?? [];
    $cover = $media['cover'] ?? ($media[0] ?? null);
    $gallery = collect($media['gallery'] ?? $media)->filter(fn ($p) => is_string($p))->unique()->values();
    $packages = $service->packages;
@endphp

@section('title', $service->title.' | Jasapedia')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($service->description), 150))

@section('content')
<x-ui.breadcrumb :items="[['label' => 'Beranda', 'url' => route('web.home')], ['label' => $service->category->name ?? 'Jasa', 'url' => $service->category ? route('web.explore', ['category' => $service->category->slug]) : null], ['label' => $service->title]]"/>

<div class="mt-4 flex flex-col gap-6 lg:flex-row">
    <div class="min-w-0 flex-1">
        {{-- GALLERY --}}
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-teal-100 via-teal-50 to-amber-50 shadow-sm">
            @if($cover)
                <img src="{{ app(\App\Domain\Catalog\MediaService::class)->url($cover) }}" alt="{{ $service->title }}" class="aspect-[16/9] w-full object-cover" loading="eager"/>
            @else
                <div class="flex aspect-[16/9] items-center justify-center text-teal-700/30"><x-brand.mark class="h-24 w-24"/></div>
            @endif
        </div>
        @if($gallery->count() > 1)
            <div class="mt-2.5 grid grid-cols-4 gap-2.5 sm:grid-cols-6">
                @foreach($gallery->take(6) as $img)
                    <img src="{{ app(\App\Domain\Catalog\MediaService::class)->url($img) }}" alt="Galeri {{ $loop->index + 1 }}" loading="lazy" class="aspect-square w-full rounded-xl object-cover ring-1 ring-slate-200"/>
                @endforeach
            </div>
        @endif

        {{-- TITLE --}}
        <div class="mt-5">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge tone="teal">{{ $service->category->name ?? '' }}</x-ui.badge>
                <x-ui.badge>{{ $service->delivery_mode === 'online' ? 'Online' : ($service->delivery_mode === 'hybrid' ? 'Hybrid' : 'Onsite') }}</x-ui.badge>
                @if($service->emergency_capable)<x-ui.badge tone="rose">Darurat 24/7</x-ui.badge>@endif
                @if($service->warranty_days > 0)<x-ui.badge tone="green">Garansi {{ $service->warranty_days }} hari</x-ui.badge>@endif
            </div>
            <h1 class="mt-2.5 text-xl font-extrabold leading-snug text-slate-900 sm:text-2xl">{{ $service->title }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                <span class="flex items-center gap-1.5"><x-ui.rating :value="$service->partner->rating_avg ?? 0" :count="$service->partner->rating_count ?? null"/></span>
                <span>{{ $service->partner->completed_jobs ?? 0 }} pekerjaan selesai</span>
            </div>
        </div>

        {{-- DESCRIPTION --}}
        <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm sm:p-6">
            <h2 class="font-bold text-slate-900">Deskripsi Jasa</h2>
            <p class="mt-2.5 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $service->description }}</p>

            @if($service->inclusions)
                <h3 class="mt-5 text-sm font-bold text-slate-900">Termasuk</h3>
                <ul class="mt-1.5 space-y-1 text-sm text-slate-600">
                    @foreach((array) $service->inclusions as $inc)
                        <li class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>{{ $inc }}</li>
                    @endforeach
                </ul>
            @endif
            @if($service->exclusions)
                <h3 class="mt-4 text-sm font-bold text-slate-900">Tidak Termasuk</h3>
                <ul class="mt-1.5 space-y-1 text-sm text-slate-500">
                    @foreach((array) $service->exclusions as $exc)
                        <li class="flex items-start gap-2"><svg class="mt-0.5 h-4 w-4 shrink-0 text-rose-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>{{ $exc }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- PROVIDER CARD --}}
        <section class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">Penyedia Jasa</h2>
            <div class="mt-3 flex items-start justify-between gap-4">
                <a href="{{ route('web.provider.show', $service->partner->slug) }}" class="flex items-center gap-3.5">
                    <x-ui.avatar :name="$service->partner->display_name" :src="$service->partner->avatar_path ? app(\App\Domain\Catalog\MediaService::class)->url($service->partner->avatar_path) : null" :verified="$service->partner->isVerified()" size="lg"/>
                    <div>
                        <p class="font-bold text-slate-900 hover:text-teal-700">{{ $service->partner->display_name }}</p>
                        <p class="text-xs text-slate-500">{{ $service->partner->city ?? 'Indonesia' }} · {{ $service->partner->response_minutes <= 30 ? 'Respons cepat' : 'Respons &lt; 2 jam' }}</p>
                    </div>
                </a>
                <a href="{{ route('web.provider.show', $service->partner->slug) }}" class="shrink-0 rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:border-teal-600 hover:text-teal-700">Lihat Profil</a>
            </div>
        </section>

        {{-- REVIEWS --}}
        <section class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">Ulasan Pembeli</h2>
            @php
                $reviews = \App\Models\Review::where('partner_id', $service->partner->id)->where('status', 'published')->with('author:id,name')->latest()->take(5)->get();
            @endphp
            @forelse($reviews as $review)
                <article class="mt-4 border-t border-slate-100 pt-4 first:border-0 first:pt-0">
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :name="$review->author->name" size="sm"/>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-slate-800">{{ $review->author->name }}</p>
                            <x-ui.rating :value="$review->overall" size="xs"/>
                        </div>
                        <x-ui.badge tone="teal" class="ml-auto">Pesanan Terverifikasi</x-ui.badge>
                    </div>
                    @if($review->comment)<p class="mt-2.5 text-sm leading-relaxed text-slate-600">{{ $review->comment }}</p>@endif
                    @if($review->partner_response)
                        <div class="mt-3 rounded-xl bg-slate-50 p-3.5 text-sm">
                            <p class="text-xs font-bold text-teal-700">Tanggapan Penyedia</p>
                            <p class="mt-1 text-slate-600">{{ $review->partner_response }}</p>
                        </div>
                    @endif
                </article>
            @empty
                <p class="mt-3 text-sm text-slate-400">Belum ada ulasan untuk jasa ini.</p>
            @endforelse
        </section>
    </div>

    {{-- STICKY PURCHASE PANEL --}}
    <aside class="lg:w-96 lg:shrink-0">
        <div class="lg:sticky lg:top-24">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-md">
                <x-ui.money :amount="$service->base_price" class="text-2xl font-extrabold text-slate-900"/>
                @if($service->unit_label)<span class="text-sm text-slate-500"> / {{ $service->unit_label }}</span>@endif

                @if($packages->isNotEmpty())
                    <div class="mt-4 grid grid-cols-3 gap-1.5 rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Paket">
                        @foreach($packages as $pkg)
                            <button class="rounded-lg py-2 text-xs font-bold {{ $loop->first ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-500' }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $pkg->name }}</button>
                        @endforeach
                    </div>
                    <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                        @foreach($packages->first()->inclusions ?? [] as $inc)
                            <li class="flex items-center gap-2"><svg class="h-4 w-4 shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>{{ $inc }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('web.checkout') }}" class="mt-5 space-y-3">
                    @csrf
                    <input type="hidden" name="service_id" value="{{ $service->id }}">
                    <label class="block text-sm font-semibold text-slate-700">Jadwal Kunjungan</label>
                    <input type="datetime-local" name="scheduled_at" min="{{ now()->addHour()->format('Y-m-d\TH:i') }}" class="h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20"/>
                    <label class="flex items-center gap-2.5 text-sm text-slate-700">
                        <input type="checkbox" name="emergency" value="1" @if(! $service->emergency_capable) disabled @endif class="rounded border-slate-300 text-teal-600 focus:ring-teal-600"/>
                        Layanan darurat @if($service->emergency_capable)(+{{ (new \App\Support\Money\Money($service->emergency_surcharge))->format() }})@endif
                    </label>
                    <x-ui.textarea name="customer_note" placeholder="Catatan untuk penyedia (alamat, detail kendala, dll.)" :rows="3"/>
                    <x-ui.button type="submit" full>Beli Sekarang</x-ui.button>
                    <a href="https://wa.me/" class="block text-center text-sm font-semibold text-teal-700 hover:underline">Tanya Penyedia Dulu</a>
                </form>

                <ul class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
                    <li class="flex items-center gap-2"><svg class="h-4 w-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>Dana ditahan sampai pekerjaan selesai</li>
                    @if($service->warranty_days > 0)<li class="flex items-center gap-2"><svg class="h-4 w-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>Garansi {{ $service->warranty_days }} hari</li>@endif
                </ul>
            </div>
        </div>
    </aside>
</div>
@endsection
