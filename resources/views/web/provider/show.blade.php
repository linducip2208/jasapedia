@extends('layouts.app')

@section('title', $partner->display_name.' | Jasapedia')

@section('content')
@php
    $avatar = $partner->avatar_path ? app(\App\Domain\Catalog\MediaService::class)->url($partner->avatar_path) : null;
@endphp
<div class="overflow-hidden rounded-3xl bg-slate-900 px-6 py-10 text-white sm:px-10">
    <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
        <x-ui.avatar :name="$partner->display_name" :src="$avatar" :verified="$partner->isVerified()" size="xl"/>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-extrabold">{{ $partner->display_name }}</h1>
                <x-ui.badge tone="teal">{{ $level }}</x-ui.badge>
                @if($partner->isOnline())<x-ui.badge tone="green">Online</x-ui.badge>@endif
            </div>
            <p class="mt-1 text-sm text-slate-300">{{ $partner->city ?? 'Indonesia' }} · Anggota sejak {{ $memberSince }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-sm">
                <span class="flex items-center gap-1.5"><x-ui.rating :value="$partner->rating_avg" :count="$reviewCount" size="sm" class="[&_span]:text-slate-300"/></span>
                <span class="text-slate-300">{{ number_format($completed) }} pekerjaan selesai</span>
                <span class="text-slate-300">Respons {{ $responseTime }}</span>
            </div>
        </div>
        <div class="flex gap-2.5">
            <a href="{{ route('web.chat.index') }}" class="rounded-xl bg-white/15 px-4 py-2.5 text-sm font-bold hover:bg-white/25">Chat</a>
            <button type="button" class="rounded-xl bg-teal-500 px-4 py-2.5 text-sm font-bold hover:bg-teal-400" onclick="navigator.share ? navigator.share({url: location.href}) : (navigator.clipboard.writeText(location.href), alert('Tautan disalin!'))">Bagikan</button>
        </div>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
    <div class="min-w-0">
        <x-ui.tabs :tabs="['services' => ['label' => 'Jasa ({{ $services->total() }})'], 'reviews' => ['label' => 'Ulasan'], 'about' => ['label' => 'Tentang']]" active="services"/>

        <section id="services" class="mt-5">
            @forelse($services as $service)
                @if($loop->first)<div class="grid grid-cols-2 gap-3 sm:grid-cols-3">@endif
                <x-ui.service-card :service="$service"/>
                @if($loop->last)</div>@endif
            @empty
                <x-ui.empty-state title="Belum ada jasa aktif" description="Penyedia ini belum mempublikasikan jasa."/>
            @endforelse
            <x-ui.pagination :paginator="$services"/>
        </section>
    </div>

    <aside class="space-y-4">
        <x-ui.card>
            <h2 class="font-bold text-slate-900">Informasi</h2>
            <dl class="mt-3 space-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Tipe</dt><dd class="font-semibold text-slate-800">{{ $partner->isVendorCompany() ? 'Perusahaan' : ($partner->type === 'individual' ? 'Teknisi Individu' : 'Freelancer') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Pekerjaan Selesai</dt><dd class="font-semibold text-slate-800">{{ number_format($completed) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Status Verifikasi</dt><dd><x-ui.badge tone="{{ $partner->isVerified() ? 'green' : 'amber' }}">{{ $partner->isVerified() ? 'Terverifikasi' : 'Proses' }}</x-ui.badge></dd></div>
                @if($partner->organization)<div class="flex justify-between gap-3"><dt class="text-slate-500">Tim</dt><dd class="font-semibold text-slate-800">{{ $partner->organization->worker_count }} orang</dd></div>@endif
            </dl>
        </x-ui.card>

        @if($partner->skills->isNotEmpty())
            <x-ui.card>
                <h2 class="font-bold text-slate-900">Keahlian</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($partner->skills as $skill)
                        <x-ui.chip>{{ $skill->name }}</x-ui.chip>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        @if($partner->serviceAreas->isNotEmpty())
            <x-ui.card>
                <h2 class="font-bold text-slate-900">Area Layanan</h2>
                <ul class="mt-2.5 space-y-1 text-sm text-slate-600">
                    @foreach($partner->serviceAreas as $area)
                        <li>{{ $area->location?->name ?? ($area->coverage_type === 'radius' ? 'Radius '.$area->radius_km.' km' : ucfirst($area->coverage_type)) }}</li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </aside>
</div>

<section class="mt-8">
    <h2 class="text-lg font-extrabold text-slate-900">Ulasan Terbaru</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        @forelse($reviews as $review)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <x-ui.avatar :name="$review->author->name" size="sm"/>
                    <div><p class="text-sm font-bold text-slate-800">{{ $review->author->name }}</p><x-ui.rating :value="$review->overall" size="xs"/></div>
                </div>
                @if($review->comment)<p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $review->comment }}</p>@endif
            </article>
        @empty
            <p class="text-sm text-slate-400">Belum ada ulasan.</p>
        @endforelse
    </div>
</section>
@endsection
