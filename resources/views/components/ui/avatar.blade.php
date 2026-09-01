@props(['name' => null, 'src' => null, 'size' => 'md', 'verified' => false])
@php
$sizes = ['xs' => 'h-6 w-6 text-[10px]', 'sm' => 'h-8 w-8 text-xs', 'md' => 'h-11 w-11 text-sm', 'lg' => 'h-14 w-14 text-base', 'xl' => 'h-20 w-20 text-2xl'];
$initial = $name ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
@endphp
<span class="relative inline-block shrink-0">
    @if($src)
        <img src="{{ $src }}" alt="{{ $name }}" class="{{ $sizes[$size] ?? $sizes['md'] }} rounded-full object-cover" loading="lazy"/>
    @else
        <span class="{{ $sizes[$size] ?? $sizes['md'] }} flex items-center justify-center rounded-full bg-gradient-to-br from-teal-500 to-teal-700 font-bold text-white">{{ $initial }}</span>
    @endif
    @if($verified)
        <span class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-teal-600 ring-2 ring-white" title="Terverifikasi">
            <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><path d="m5 13 4 4L19 7"/></svg>
        </span>
    @endif
</span>
