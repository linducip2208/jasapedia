@props(['service'])
@php
$cover = $service->cover_image ? \Illuminate\Support\Facades\Storage::disk(config('services.media.disk', 'public'))->url($service->cover_image) : null;
@endphp
<article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
    <a href="{{ route('web.service', $service) }}" class="block">
        <x-ui.service-image :service="$service" aspect="aspect-[16/10]" class="w-full">
            @if($service->emergency_capable)
                <span class="absolute left-2.5 top-2.5 rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Darurat 24/7</span>
            @endif
        </x-ui.service-image>
        <div class="p-3.5">
            <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-teal-700">{{ $service->category->name ?? '' }}</p>
            <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-slate-900 group-hover:text-teal-700">{{ $service->title }}</h3>
            <div class="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                <x-ui.rating :value="$service->rating_avg ?? 0" size="xs"/>
                <span>A•</span><span>{{ $service->orders_count ?? 0 }} pesanan</span>
            </div>
            <div class="mt-2 flex items-center justify-between">
                <x-ui.money :amount="$service->base_price" class="text-sm font-bold text-slate-900"/>
                <span class="text-[11px] text-slate-400">{{ \Illuminate\Support\Str::limit($service->partner?->display_name ?? 'Penyedia', 18) }}</span>
            </div>
        </div>
    </a>
</article>
