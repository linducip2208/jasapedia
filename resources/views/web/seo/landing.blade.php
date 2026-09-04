@extends('layouts.app')

@section('title', $title)

@section('meta')
    <meta name="description" content="{{ $seo?->meta_description ?? $intro }}">
    <link rel="canonical" href="{{ $seo?->canonical_url ?? url()->current() }}">
    @if($seo?->noindex)
        <meta name="robots" content="noindex, follow">
    @endif
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $seo?->meta_description ?? $intro }}">
    @if($seo?->og_image)<meta property="og:image" content="{{ $seo->og_image }}">@endif
@endsection

@section('content')
<x-ui.breadcrumb :items="[['label' => 'Beranda', 'url' => route('web.home')], ['label' => 'Jelajahi', 'url' => route('web.explore')], ['label' => $category->name], ['label' => $city->name]]"/>

<header class="mt-5 max-w-3xl">
    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ $h1 }}</h1>
    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $intro }}</p>
    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('web.explore', ['category' => $category->slug, 'city' => $city->name]) }}" class="rounded-xl bg-teal-600 px-4 py-2 font-bold text-white hover:bg-teal-500">Lihat semua dengan filter</a>
        <a href="{{ route('web.requests.create') }}" class="rounded-xl border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:border-teal-600 hover:text-teal-700">Posting Kebutuhan</a>
    </div>
</header>

<section class="mt-8" aria-label="Daftar layanan">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-extrabold text-slate-900">{{ $services->count() }} penyedia terbaik {{ $category->name }} di {{ $city->name }}</h2>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @forelse($services as $service)
            <x-ui.service-card :service="$service"/>
        @empty
            <div class="col-span-2 lg:col-span-4">
                <x-ui.empty-state
                    title="Belum ada penyedia di kota ini"
                    description="Coba jelajahi kategori ini di kota lain, atau posting kebutuhan agar penyedia menemukanmu."/>
            </div>
        @endforelse
    </div>
</section>

<nav class="mt-10 rounded-2xl border border-slate-200 bg-white p-5" aria-label="Kota lainnya">
    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">{{ $category->name }} di kota lain</h2>
    <div class="mt-3 flex flex-wrap gap-2">
        @foreach($siblingCities as $sibling)
            <a href="{{ route('web.seo.landing-city', [$category->slug, $sibling->slug]) }}"
               class="rounded-full border border-slate-200 px-3.5 py-1.5 text-sm font-semibold text-slate-700 hover:border-teal-600 hover:text-teal-700">
                {{ $category->name }} {{ $sibling->name }}
            </a>
        @endforeach
    </div>
</nav>
@endsection
