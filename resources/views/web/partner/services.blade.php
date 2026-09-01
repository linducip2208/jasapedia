@extends('layouts.partner')

@section('title', 'Jasa Saya | Jasapedia')

@section('partner-content')
<div class="flex items-center justify-between">
    <h1 class="text-xl font-extrabold text-slate-900">Jasa Saya</h1>
    <a href="{{ route('web.partner.services.create') }}" class="rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-teal-700">+ Jasa Baru</a>
</div>

<div class="mt-4 space-y-3">
    @forelse($services as $service)
        <article class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex min-w-0 items-center gap-3.5">
                @php $cover = ($service->media['cover'] ?? null); @endphp
                @if($cover)
                    <img src="{{ app(\App\Domain\Catalog\MediaService::class)->url($cover) }}" alt="" class="h-14 w-20 shrink-0 rounded-xl object-cover" loading="lazy"/>
                @else
                    <span class="flex h-14 w-20 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-400"><x-brand.mark class="h-7 w-7"/></span>
                @endif
                <div class="min-w-0">
                    <p class="truncate font-semibold text-slate-900">{{ $service->title }}</p>
                    <p class="text-xs text-slate-500">{{ $service->category->name ?? '' }} · <x-ui.money :amount="$service->base_price"/></p>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <x-ui.badge tone="{{ $service->status === 'active' ? 'green' : 'amber' }}">{{ $service->status === 'active' ? 'Aktif' : 'Jeda' }}</x-ui.badge>
                <form method="POST" action="{{ route('web.partner.services.toggle', $service->id) }}">
                    @csrf
                    <x-ui.button type="submit" variant="outline" size="sm">{{ $service->status === 'active' ? 'Jeda' : 'Aktifkan' }}</x-ui.button>
                </form>
            </div>
        </article>
    @empty
        <x-ui.empty-state title="Belum ada jasa" description="Buat jasa pertamamu agar pelanggan menemukanmu." actionUrl="{{ route('web.partner.services.create') }}" actionLabel="Buat Jasa"/>
    @endforelse
</div>
<x-ui.pagination :paginator="$services"/>
@endsection
