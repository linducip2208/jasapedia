@props([
    'variant' => 'primary', // primary | secondary | outline | ghost | danger | success
    'size' => 'md',         // sm | md | lg
    'type' => 'button',
    'full' => false,
])
@php
$base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-xl transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-teal-600 disabled:opacity-50 disabled:pointer-events-none';
$sizes = ['sm' => 'h-9 px-3.5 text-sm', 'md' => 'h-11 px-5 text-sm', 'lg' => 'h-12 px-6 text-base'];
$variants = [
    'primary' => 'bg-teal-600 text-white hover:bg-teal-700 active:bg-teal-800 shadow-sm',
    'secondary' => 'bg-slate-900 text-white hover:bg-slate-800 shadow-sm',
    'outline' => 'border border-slate-300 bg-white text-slate-700 hover:border-teal-600 hover:text-teal-700',
    'ghost' => 'text-slate-700 hover:bg-slate-100',
    'danger' => 'bg-rose-600 text-white hover:bg-rose-700 shadow-sm',
    'success' => 'bg-amber-500 text-white hover:bg-amber-600 shadow-sm',
];
$classes = trim(($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']).($full ? ' w-full' : ''));
@endphp
<button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }}>{{ $slot }}</button>
