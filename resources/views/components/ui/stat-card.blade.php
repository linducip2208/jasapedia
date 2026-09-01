@props(['label', 'value', 'sub' => null, 'tone' => 'slate'])
@php
$tones = ['slate' => 'text-slate-900', 'teal' => 'text-teal-700', 'amber' => 'text-amber-600', 'rose' => 'text-rose-600'];
@endphp
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p>
    <p class="mt-1.5 text-2xl font-extrabold {{ $tones[$tone] ?? $tones['slate'] }}">{{ $value }}</p>
    @if($sub)<p class="mt-1 text-xs text-slate-500">{{ $sub }}</p>@endif
</div>
