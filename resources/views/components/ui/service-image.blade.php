@props([
    'service',
    'aspect' => 'aspect-[16/10]',
    'loading' => 'lazy',
])

@php
    // Central demo/production image resolver with a branded, category-aware
    // fallback — never a broken icon, blank box, or giant emoji.
    $media = $service->media ?? [];
    $coverPath = $media['cover'] ?? (is_string($media[0] ?? null) ? $media[0] : null);
    $iconKey = $service->category->icon ?? null;
    $label = $service->category->name ?? 'Jasapedia';
    $valid = $coverPath && file_exists(public_path($coverPath));
@endphp

@php($resolver = app(\App\Domain\Catalog\MediaService::class))

<span {{ $attributes->merge(['class' => 'relative block overflow-hidden bg-gradient-to-br from-teal-100 via-teal-50 to-amber-50 '.$aspect]) }}>
    @if($valid)
        <img src="{{ $resolver->url($coverPath) }}"
             alt="{{ $service->title }}"
             loading="{{ $loading }}"
             class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"/>
    @elseif($coverPath)
        <img src="{{ $resolver->url($coverPath) }}" alt="{{ $service->title }}" loading="{{ $loading }}"
             onerror="this.remove()"
             class="absolute inset-0 h-full w-full object-cover"/>
        <x-brand.category-icon :icon="$iconKey" class="absolute left-1/2 top-1/2 h-14 w-14 -translate-x-1/2 -translate-y-1/2 text-teal-700/25"/>
    @else
        <x-brand.category-icon :icon="$iconKey" class="absolute left-1/2 top-1/2 h-14 w-14 -translate-x-1/2 -translate-y-1/2 text-teal-700/30"/>
        <span class="absolute bottom-2 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-white/70 px-2 py-0.5 text-[10px] font-bold text-teal-800">{{ $label }}</span>
    @endif

    @isset($slot)
        {{ $slot }}
    @endisset
</span>
