@props(['tone' => 'info'])
@php
$tones = ['info' => 'border-sky-200 bg-sky-50 text-sky-800', 'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'warning' => 'border-amber-200 bg-amber-50 text-amber-800', 'danger' => 'border-rose-200 bg-rose-50 text-rose-800'];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm font-medium '.$tones[$tone]]) }} role="alert">
    @isset($title)<p class="font-bold">{{ $title }}</p>@endisset
    {{ $slot }}
</div>
