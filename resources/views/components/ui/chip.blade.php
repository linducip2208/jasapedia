@props(['active' => false, 'href' => null])
@php
$classes = 'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition '.($active ? 'border-teal-600 bg-teal-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-teal-600 hover:text-teal-700');
@endphp
@if($href)<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>@else<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>@endif
